<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiToolAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('services.ai_tool.access_key', '');
        $headerName = (string) config('services.ai_tool.header', 'X-AI-Tool-Key');
        $providedKey = (string) $request->header($headerName, '');

        if ($configuredKey === '' || $providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized AI tool access',
            ], 401);
        }

        return $next($request);
    }
}
