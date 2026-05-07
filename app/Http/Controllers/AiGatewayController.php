<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskchatRequest;
use App\Http\Requests\SearchArchivesToolRequest;
use App\Services\AiArchiveSearchService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiGatewayController extends Controller
{
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

    public function askChat(AskchatRequest $request)
    {
        $validated = $request->validated();
        $traceId = $this->resolveTraceId($request);
        $aiBaseUrl = rtrim((string) config('services.ai_gateway.base_url', 'http://localhost:5000'), '/');
        $aiTimeout = (int) config('services.ai_gateway.timeout', 30);

        $payload = [
            'message' => trim((string) $validated['message']),
            'use_search' => (bool) ($validated['use_search'] ?? false),
        ];

        if (array_key_exists('temperature', $validated) && $validated['temperature'] !== null) {
            $payload['temperature'] = (float) $validated['temperature'];
        }

        if (array_key_exists('context', $validated)) {
            $payload['context'] = $validated['context'];
        }

        try {
            $response = Http::acceptJson()
                ->timeout($aiTimeout)
                ->withHeader('X-Trace-Id', $traceId)
                ->post("{$aiBaseUrl}/api/chat/ask", $payload);

            return $this->passthroughAiResponse($response, $traceId);
        } catch (ConnectionException $e) {
            Log::warning('AI chat proxy request failed', [
                'trace_id' => $traceId,
                'ai_base_url' => $aiBaseUrl,
                'error' => $e->getMessage(),
            ]);

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

        return response($response->body(), $response->status())
            ->header('Content-Type', $contentType)
            ->header('X-Trace-Id', $traceId);
    }
}
