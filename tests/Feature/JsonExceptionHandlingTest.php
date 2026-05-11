<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class JsonExceptionHandlingTest extends TestCase
{
    public function test_api_500_response_uses_generic_json_contract(): void
    {
        Route::get('/api/test-unhandled-error', function () {
            throw new RuntimeException('Sensitive internal detail');
        });

        $this->getJson('/api/test-unhandled-error')
            ->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server',
            ])
            ->assertJsonMissing([
                'message' => 'Sensitive internal detail',
            ]);
    }
}
