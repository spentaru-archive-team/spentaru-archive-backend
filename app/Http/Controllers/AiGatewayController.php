<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskAiRequest;
use App\Http\Requests\ExtractOcrRequest;
use App\Http\Requests\ExtractPdfNativeRequest;
use App\Http\Requests\SearchArchivesToolRequest;
use App\Services\AiArchiveSearchService;
use App\Services\AiGatewayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiGatewayController extends Controller
{
    public function __construct(
        private AiGatewayService $gateway,
        private AiArchiveSearchService $archiveSearchService
    ) {}

    private function resolveTraceId(Request $request): string
    {
        return (string) ($request->header('X-Trace-Id') ?: Str::uuid());
    }

    private function relayResponse(Response $response, string $successMessage)
    {
        $payload = $response->json();
        $traceId = data_get($payload, 'trace_id') ?: $response->header('X-Trace-Id') ?: Str::uuid()->toString();

        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMessage,
                'data' => data_get($payload, 'data', []),
                'trace_id' => $traceId,
            ], 200)->header('X-Trace-Id', $traceId);
        }

        $upstreamMessage = data_get($payload, 'error.message') ?: 'AI service gagal memproses request';
        $statusCode = $response->status();

        if ($statusCode < 400 || $statusCode >= 500) {
            $statusCode = 502;
        }

        return response()->json([
            'status' => 'error',
            'message' => $upstreamMessage,
            'errors' => data_get($payload, 'error.details'),
            'trace_id' => $traceId,
        ], $statusCode)->header('X-Trace-Id', $traceId);
    }

    public function health(Request $request)
    {
        $traceId = $this->resolveTraceId($request);

        try {
            $response = $this->gateway->health($traceId);

            return $this->relayResponse($response, 'sukses mengambil status AI service');
        } catch (ConnectionException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI service tidak dapat dihubungi',
                'trace_id' => $traceId,
            ], 504)->header('X-Trace-Id', $traceId);
        }
    }

    public function ask(AskAiRequest $request)
    {
        $validated = $request->validated();
        $traceId = $this->resolveTraceId($request);

        try {
            $response = $this->gateway->askChat([
                'message' => $validated['message'],
                'context' => $validated['context'] ?? null,
                'use_search' => $validated['use_search'] ?? false,
            ], $traceId);

            return $this->relayResponse($response, 'sukses mendapatkan jawaban AI');
        } catch (ConnectionException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI service tidak dapat dihubungi',
                'trace_id' => $traceId,
            ], 504)->header('X-Trace-Id', $traceId);
        }
    }

    public function extractOcr(ExtractOcrRequest $request)
    {
        $validated = $request->validated();
        $traceId = $this->resolveTraceId($request);

        try {
            $response = $this->gateway->extractOcr($validated['file'], $traceId);

            return $this->relayResponse($response, 'sukses mengekstrak teks OCR');
        } catch (ConnectionException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI service tidak dapat dihubungi',
                'trace_id' => $traceId,
            ], 504)->header('X-Trace-Id', $traceId);
        }
    }

    public function extractPdfNative(ExtractPdfNativeRequest $request)
    {
        $validated = $request->validated();
        $traceId = $this->resolveTraceId($request);

        try {
            $response = $this->gateway->extractPdfNative($validated['file'], $traceId);

            return $this->relayResponse($response, 'sukses mengekstrak teks PDF native');
        } catch (ConnectionException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI service tidak dapat dihubungi',
                'trace_id' => $traceId,
            ], 504)->header('X-Trace-Id', $traceId);
        }
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
}
