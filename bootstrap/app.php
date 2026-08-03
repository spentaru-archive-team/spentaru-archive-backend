<?php

use App\Http\Middleware\Admin;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAiToolAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(prepend: [
            StartSession::class,
            ShareErrorsFromSession::class,
        ]);
        $middleware->append(EnsureFrontendRequestsAreStateful::class);

        $middleware->alias([
            'admin' => Admin::class,
            'auth' => Authenticate::class,
            'ai.tool' => EnsureAiToolAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->is('sanctum/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() ?: 'Forbidden',
            ], 403);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource tidak ditemukan',
            ], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return response()->json([
                'status' => 'error',
                'message' => 'Method tidak diizinkan untuk endpoint ini',
            ], 405);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $headers = $e->getHeaders();
            $retryAfter = (int) ($headers['Retry-After'] ?? 60);

            return response()->json([
                'status' => 'error',
                'message' => 'Terlalu banyak request. Silakan tunggu beberapa saat.',
                'quota_info' => 'rate_limited: too_many_requests',
                'retry_after_seconds' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter);
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $origin = $request->header('Origin');
            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }

            return $response;
        });
    })->create();
