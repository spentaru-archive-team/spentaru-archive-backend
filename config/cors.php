<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        static fn (string $origin) => trim($origin),
        explode(',', implode(',', array_filter([
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            (string) env('CORS_ALLOWED_ORIGINS', ''),
            (string) env('FRONTEND_URL', ''),
        ])))
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-AI-Tool-Key', 'X-Trace-Id'],

    'exposed_headers' => ['X-Trace-Id'],

    'max_age' => 3600,

    'supports_credentials' => true,

];
