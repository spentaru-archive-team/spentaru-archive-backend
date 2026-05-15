<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskChatRequest;
use App\Http\Requests\SearchArchivesToolRequest;
use App\Services\AiArchiveSearchService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class AiGatewayController extends Controller
{
    private const CHAT_HISTORY_LIMIT = 100;

    private const CHAT_REQUEST_LIMIT = 50;

    public function __construct(
        private AiArchiveSearchService $archiveSearchService
    ) {}

    private function resolveTraceId(Request $request): string
    {
        return (string) ($request->header('X-Trace-Id') ?: Str::uuid());
    }

    public function searchArchivesTool(SearchArchivesToolRequest $request)
    {
        $validated = $request->validated();
        $traceId = $this->resolveTraceId($request);
        $result = $this->archiveSearchService->search(
            $validated['question'],
            $validated['limit'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mencari arsip untuk AI tool',
            'data' => $result,
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function askChat(AskChatRequest $request)
    {
        $validated = $request->validated();
        $traceId = trim((string) $validated['x_trace_id']);
        $aiBaseUrl = rtrim((string) config('services.ai_gateway.base_url', 'http://localhost:5000'), '/');
        $aiTimeout = (int) config('services.ai_gateway.timeout', 30);
        $sessionId = 'user:'.Auth::id();
        $messageKey = "chat:session:{$sessionId}:messages";
        $countKey = "chat:session:{$sessionId}:request_count";
        $requestCount = (int) Redis::get($countKey);

        if ($requestCount >= self::CHAT_REQUEST_LIMIT) {
            Redis::del($messageKey);
            Redis::del($countKey);
        }

        Redis::rpush($messageKey, json_encode([
            'role' => 'user',
            'content' => $validated['message'],
        ]));

        $messages = collect(Redis::lrange($messageKey, -self::CHAT_HISTORY_LIMIT, -1))
            ->map(fn ($item) => json_decode((string) $item, true))
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->all();

        $payload = [
            'message' => $messages,
        ];

        if (array_key_exists('use_search', $validated)) {
            $payload['use_search'] = $validated['use_search'];
        }

        try {
            $aiServiceKey = config('services.ai_gateway.api_key', '');

            $http = Http::acceptJson()
                ->timeout($aiTimeout)
                ->withHeader('X-Trace-Id', $traceId);

            if ($aiServiceKey) {
                $http->withHeader('X-AI-Service-Key', $aiServiceKey);
            }

            $response = $http->post("{$aiBaseUrl}/api/chat/ask", $payload);

            $status = $response->status();

            if ($status === 429) {
            }

            if ($response->successful()) {
                $answer = data_get($response->json(), 'data.answer');
                if (is_string($answer) && trim($answer) !== '') {
                    Redis::rpush($messageKey, json_encode([
                        'role' => 'assistant',
                        'content' => $answer,
                    ]));
                }

                Redis::incr($countKey);
            } else {
                Redis::rpop($messageKey);
            }

            return $this->passthroughAiResponse($response, $traceId);
        } catch (ConnectionException $e) {
            Log::warning('AI chat proxy request failed', [
                'trace_id' => $traceId,
                'ai_base_url' => $aiBaseUrl,
                'error' => $e->getMessage(),
            ]);

            Redis::rpop($messageKey);

            return response()->json([
                'status' => 'error',
                'message' => 'AI Service unreachable',
                'error' => 'AI Service unreachable',
                'trace_id' => $traceId,
            ], 502)->header('X-Trace-Id', $traceId);
        }
    }

    private function passthroughAiResponse(HttpResponse $response, string $fallbackTraceId)
    {
        $traceId = $response->header('X-Trace-Id')
            ?: data_get($response->json(), 'trace_id')
            ?: $fallbackTraceId;
        $contentType = $response->header('Content-Type') ?: 'application/json';

        $laravelResponse = response($response->body(), $response->status())
            ->header('Content-Type', $contentType)
            ->header('X-Trace-Id', $traceId);

        if ($response->header('Retry-After')) {
            $laravelResponse->header('Retry-After', $response->header('Retry-After'));
        }

        return $laravelResponse;
    }
}
