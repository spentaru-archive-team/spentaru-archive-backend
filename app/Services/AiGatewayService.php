<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class AiGatewayService
{
    private function resolveFileContents(UploadedFile $file): string
    {
        $contents = @file_get_contents($file->getPathname());

        if ($contents === false) {
            return '';
        }

        return $contents;
    }

    private function client(?string $traceId = null): PendingRequest
    {
        $headers = [];

        if ($traceId) {
            $headers['X-Trace-Id'] = $traceId;
        }

        return Http::baseUrl((string) config('services.ai_gateway.base_url'))
            ->acceptJson()
            ->timeout((int) config('services.ai_gateway.timeout', 15))
            ->withHeaders($headers);
    }

    public function health(?string $traceId = null): Response
    {
        return $this->client($traceId)->get('/health');
    }

    public function askChat(array $payload, ?string $traceId = null): Response
    {
        return $this->client($traceId)->post('/api/chat/ask', $payload);
    }

    public function extractOcr(UploadedFile $file, ?string $traceId = null): Response
    {
        $fileContent = fopen($file->getPathname(), 'r');

        return $this->client($traceId)
            ->attach(
                'file',
                $fileContent !== false ? $fileContent : $this->resolveFileContents($file),
                $file->getClientOriginalName()
            )
            ->post('/api/ocr/extract');
    }

    public function extractPdfNative(UploadedFile $file, ?string $traceId = null): Response
    {
        $fileContent = fopen($file->getPathname(), 'r');

        return $this->client($traceId)
            ->attach(
                'file',
                $fileContent !== false ? $fileContent : $this->resolveFileContents($file),
                $file->getClientOriginalName()
            )
            ->post('/api/pdf/extract-native');
    }
}
