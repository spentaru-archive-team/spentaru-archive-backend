<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\Cabinet;
use App\Models\Event;
use App\Models\Rack;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiGatewayApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::del(
            'chat:session:user:1:messages',
            'chat:session:user:1:request_count',
        );
    }

    private function authenticatedUser(string $role = 'guru'): User
    {
        return (new User([
            'name' => 'AI Tester',
            'subject' => 'Informatika',
            'position' => 'Guru',
            'username' => 'ai_tester',
            'password' => 'Password123',
            'role' => $role,
        ]))->forceFill(['id' => 1]);
    }

    private function createArchiveSearchFixture(): Archive
    {
        $owner = User::create([
            'name' => 'Guru Arsip',
            'subject' => 'Sejarah',
            'position' => 'Guru',
            'username' => 'guru_arsip',
            'password' => 'Password123',
            'role' => 'guru',
        ]);

        $category = ArchiveCategory::create([
            'name' => 'Kesiswaan',
            'description' => 'Arsip siswa',
            'has_subcategory' => true,
        ]);

        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Dokumentasi',
        ]);

        $event = Event::create([
            'title' => 'Peringatan 17 Agustus',
            'user_id' => $owner->id,
            'description' => 'Upacara hari kemerdekaan',
            'date' => now()->toDateString(),
            'status' => 'done',
            'softfile_status' => 'uploaded',
        ]);

        $cabinet = Cabinet::create([
            'cabinet_number' => 1,
            'name' => 'Lemari Arsip Utama',
        ]);

        $rack = Rack::create([
            'cabinet_id' => $cabinet->id,
            'rack_number' => 2,
            'capacity' => 20,
            'used_capacity' => 1,
        ]);

        $archive = Archive::create([
            'title' => 'Merayakan Hari Kemerdekaan',
            'year' => 2025,
            'notes' => 'Dokumentasi kegiatan upacara dan daftar petugas',
            'event_id' => $event->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'uploader' => $owner->id,
            'retention_due_date' => '2035-01-01',
            'retention_status' => 'active',
        ]);

        $archive->ocrText()->create([
            'extracted_text' => 'Dokumen ini memiliki tanda tangan kepala sekolah Ahmad Mahmud pada halaman akhir.',
            'vector_id' => 'vector-archive-'.$archive->id,
        ]);

        $archive->files()->create([
            'file_name' => 'merdeka.pdf',
            'file_size' => 102400,
            'file_type' => 'pdf',
        ]);

        $archive->physicalLocation()->create([
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 4,
            'label_code' => 'L1-R2-S4',
            'notes' => 'Baris depan lemari utama',
        ]);

        return $archive;
    }

    public function test_chat_ask_requires_authentication(): void
    {
        $this->postJson('/api/v1/chat/ask', [
            'message' => 'Halo AI',
        ])->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ]);
    }

    public function test_chat_ask_passthroughs_ai_response_and_trace_id(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        config()->set('services.ai_gateway.base_url', 'http://ai-service.test');
        config()->set('services.ai_gateway.timeout', 30);

        Http::fake(function (Request $request) {
            $this->assertSame('trace-test-001', $request->header('X-Trace-Id')[0] ?? null);
            $this->assertSame([
                [
                    'role' => 'user',
                    'content' => 'Halo AI',
                ],
            ], $request['message']);
            $this->assertTrue((bool) $request['use_search']);
            $this->assertSame('http://ai-service.test/api/chat/ask', $request->url());

            return Http::response([
                'status' => true,
                'data' => [
                    'answer' => 'Halo juga, ada yang bisa dibantu?',
                    'model_or_engine' => 'RuleBased Assistant + Optional Laravel Search',
                    'sources' => [],
                ],
                'trace_id' => 'trace-ai-service-001',
            ], 200, [
                'X-Trace-Id' => 'trace-ai-service-001',
                'Content-Type' => 'application/json',
            ]);
        });

        $response = $this->withHeader('X-Trace-Id', 'trace-test-001')
            ->postJson('/api/v1/chat/ask', [
                'message' => 'Halo AI',
                'use_search' => true,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'trace_id' => 'trace-ai-service-001',
                'data' => [
                    'answer' => 'Halo juga, ada yang bisa dibantu?',
                ],
            ]);

        $this->assertSame('trace-ai-service-001', $response->headers->get('X-Trace-Id'));
        $this->assertSame([
            [
                'role' => 'user',
                'content' => 'Halo AI',
            ],
            [
                'role' => 'assistant',
                'content' => 'Halo juga, ada yang bisa dibantu?',
            ],
        ], collect(Redis::lrange('chat:session:user:1:messages', 0, -1))
            ->map(fn ($item) => json_decode((string) $item, true))
            ->all());
    }

    public function test_chat_ask_passthroughs_upstream_error_response(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([
                'status' => false,
                'error' => [
                    'message' => 'upstream internal error',
                ],
                'trace_id' => 'trace-upstream-500',
            ], 500),
        ]);

        $this->withHeader('X-Trace-Id', 'trace-upstream-500')
            ->postJson('/api/v1/chat/ask', [
                'message' => 'Tes error',
            ])->assertStatus(500)
            ->assertJson([
                'status' => false,
                'error' => [
                    'message' => 'upstream internal error',
                ],
                'trace_id' => 'trace-upstream-500',
            ]);
    }

    public function test_chat_ask_returns_502_when_ai_service_unreachable(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        Http::fake(function () {
            throw new ConnectionException('connection failed');
        });

        $this->withHeader('X-Trace-Id', 'trace-chat-down-001')
            ->postJson('/api/v1/chat/ask', [
                'message' => 'Tes down',
            ])->assertStatus(502)
            ->assertJson([
                'status' => 'error',
                'message' => 'AI Service unreachable',
                'error' => 'AI Service unreachable',
                'trace_id' => 'trace-chat-down-001',
            ]);
    }

    public function test_chat_ask_validates_payload(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/chat/ask', [
            'message' => '',
            'use_search' => 'ya',
        ])->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors([
                'x_trace_id',
                'message',
                'use_search',
            ]);
    }

    public function test_chat_ask_requires_x_trace_id_header(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/chat/ask', [
            'message' => 'Halo AI',
            'use_search' => true,
        ])->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors([
                'x_trace_id',
            ]);
    }

    public function test_legacy_ai_chat_route_alias_still_works(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([
                'status' => true,
                'data' => [
                    'answer' => 'Lewat alias lama',
                ],
                'trace_id' => 'trace-legacy-001',
            ], 200, [
                'X-Trace-Id' => 'trace-legacy-001',
            ]),
        ]);

        $this->withHeader('X-Trace-Id', 'trace-legacy-client-001')
            ->postJson('/api/v1/ai/chat/ask', [
                'message' => 'Tes alias',
            ])->assertOk()
            ->assertJsonPath('data.answer', 'Lewat alias lama');
    }

    public function test_ai_tool_archive_search_requires_internal_header(): void
    {
        config()->set('services.ai_tool.access_key', 'rahasia-ai');
        config()->set('services.ai_tool.header', 'X-AI-Tool-Key');

        $this->postJson('/api/v1/ai/tools/archives/search', [
            'question' => 'cari arsip kemerdekaan',
        ])->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthorized AI tool access',
            ]);
    }

    public function test_ai_tool_archive_search_validates_question(): void
    {
        config()->set('services.ai_tool.access_key', 'rahasia-ai');
        config()->set('services.ai_tool.header', 'X-AI-Tool-Key');

        $this->withHeader('X-AI-Tool-Key', 'rahasia-ai')
            ->postJson('/api/v1/ai/tools/archives/search', [
                'limit' => 5,
            ])->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors(['question']);
    }

    public function test_ai_tool_archive_search_returns_ranked_match_with_complete_response(): void
    {
        config()->set('services.ai_tool.access_key', 'rahasia-ai');
        config()->set('services.ai_tool.header', 'X-AI-Tool-Key');

        $archive = $this->createArchiveSearchFixture();

        Archive::create([
            'title' => 'Notulen Rapat Bulanan',
            'year' => 2024,
            'notes' => 'Arsip rapat internal',
            'category_id' => $archive->category_id,
            'subcategory_id' => $archive->subcategory_id,
            'event_id' => $archive->event_id,
            'uploader' => $archive->uploader,
            'retention_due_date' => '2034-01-01',
            'retention_status' => 'active',
        ]);

        $response = $this->withHeader('X-AI-Tool-Key', 'rahasia-ai')
            ->withHeader('X-Trace-Id', 'trace-ai-tool-001')
            ->postJson('/api/v1/ai/tools/archives/search', [
                'question' => 'Cari arsip judul "Merayakan Hari Kemerdekaan" dan OCR berisi tanda tangan Ahmad Mahmud, sekalian lokasi fisiknya',
                'limit' => 3,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses mencari arsip untuk AI tool',
                'trace_id' => 'trace-ai-tool-001',
            ])
            ->assertJsonPath('data.total_matches', 1)
            ->assertJsonPath('data.archives.0.title', 'Merayakan Hari Kemerdekaan')
            ->assertJsonPath('data.archives.0.physical_location.label_code', 'L1-R2-S4')
            ->assertJsonPath('data.archives.0.physical_location.slot_number', 4)
            ->assertJsonPath('data.archives.0.physical_location.cabinet.name', 'Lemari Arsip Utama')
            ->assertJsonPath('data.archives.0.physical_location.rack.rack_number', 2)
            ->assertJsonPath('data.archives.0.file.file_name', 'merdeka.pdf');

        $this->assertNotEmpty($response->json('data.resolved_terms'));
        $this->assertNotEmpty($response->json('data.archives.0.match_reasons'));
        $this->assertStringContainsString('Ahmad Mahmud', (string) $response->json('data.archives.0.ocr_excerpt'));
        $this->assertStringContainsString('Lokasi fisiknya', (string) $response->json('data.suggested_answer'));
    }
}
