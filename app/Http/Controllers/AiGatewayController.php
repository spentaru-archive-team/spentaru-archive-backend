<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchArchivesToolRequest;
use App\Services\AiArchiveSearchService;
use Illuminate\Http\Request;
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
}
