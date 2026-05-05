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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiGatewayApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(string $role = 'guru'): User
    {
        return new User([
            'id' => 1,
            'name' => 'AI Tester',
            'subject' => 'Informatika',
            'position' => 'Guru',
            'username' => 'ai_tester',
            'password' => 'Password123',
            'role' => $role,
        ]);
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

    public function test_ai_chat_requires_authentication(): void
    {
        $this->postJson('/api/v1/ai/chat/ask', [
            'message' => 'Halo AI',
        ])->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ]);
    }

    public function test_ai_chat_relays_success_payload_and_trace_id(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        Http::fake(function (Request $request) {
            $this->assertSame('trace-test-001', $request->header('X-Trace-Id')[0] ?? null);
            $this->assertSame('Halo AI', $request['message']);
            $this->assertFalse((bool) $request['use_search']);

            return Http::response([
                'status' => true,
                'data' => [
                    'answer' => 'Halo juga, ada yang bisa dibantu?',
                    'model_or_engine' => 'RuleBased Assistant + Optional Laravel Search',
                    'sources' => [],
                ],
                'trace_id' => 'trace-ai-service-001',
            ], 200);
        });

        $response = $this->withHeader('X-Trace-Id', 'trace-test-001')
            ->postJson('/api/v1/ai/chat/ask', [
                'message' => 'Halo AI',
                'use_search' => false,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses mendapatkan jawaban AI',
                'trace_id' => 'trace-ai-service-001',
                'data' => [
                    'answer' => 'Halo juga, ada yang bisa dibantu?',
                ],
            ]);

        $this->assertSame('trace-ai-service-001', $response->headers->get('X-Trace-Id'));
    }

    public function test_ai_chat_maps_upstream_server_error_to_502(): void
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

        $this->postJson('/api/v1/ai/chat/ask', [
            'message' => 'Tes error',
        ])->assertStatus(502)
            ->assertJson([
                'status' => 'error',
                'message' => 'upstream internal error',
                'trace_id' => 'trace-upstream-500',
            ]);
    }

    public function test_ai_health_returns_gateway_error_when_service_unreachable(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        Http::fake(function () {
            throw new ConnectionException('connection failed');
        });

        $this->withHeader('X-Trace-Id', 'trace-health-001')
            ->getJson('/api/v1/ai/health')
            ->assertStatus(504)
            ->assertJson([
                'status' => 'error',
                'message' => 'AI service tidak dapat dihubungi',
                'trace_id' => 'trace-health-001',
            ]);
    }

    public function test_ai_health_relays_success_payload(): void
    {
        $user = $this->authenticatedUser('admin');
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'ok',
                    'service' => 'ai-ocr-chat-service',
                    'version' => '1.0.0',
                ],
                'trace_id' => 'trace-health-ok-001',
            ], 200),
        ]);

        $this->getJson('/api/v1/ai/health')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses mengambil status AI service',
                'trace_id' => 'trace-health-ok-001',
                'data' => [
                    'status' => 'ok',
                    'service' => 'ai-ocr-chat-service',
                ],
            ]);
    }

    public function test_ai_ocr_extract_relays_success_payload(): void
    {
        $user = $this->authenticatedUser('guru');
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([
                'status' => true,
                'data' => [
                    'text' => 'NASKAH ARSIP',
                    'confidence' => 0.91,
                    'engine' => 'EasyOCR Local',
                    'is_local' => true,
                ],
                'trace_id' => 'trace-ocr-001',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('arsip.png', 100, 'image/png');

        $response = $this->post('/api/v1/ai/ocr/extract', [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
            'X-Trace-Id' => 'trace-web-ocr-001',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses mengekstrak teks OCR',
                'trace_id' => 'trace-ocr-001',
                'data' => [
                    'text' => 'NASKAH ARSIP',
                    'engine' => 'EasyOCR Local',
                ],
            ]);

        Http::assertSent(function (Request $request) {
            return str_ends_with($request->url(), '/api/ocr/extract')
                && (($request->header('X-Trace-Id')[0] ?? null) === 'trace-web-ocr-001');
        });
    }

    public function test_ai_ocr_extract_validates_required_file(): void
    {
        $user = $this->authenticatedUser('guru');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/ai/ocr/extract', [])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors(['file']);
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

    public function test_ai_pdf_extract_native_relays_success_payload(): void
    {
        $user = $this->authenticatedUser('guru');
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([
                'status' => true,
                'data' => [
                    'text' => 'ISI PDF ASLI',
                    'has_text' => true,
                    'engine' => 'Native PDF Extractor pypdf',
                ],
                'trace_id' => 'trace-pdf-001',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $response = $this->post('/api/v1/ai/pdf/extract-native', [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
            'X-Trace-Id' => 'trace-web-pdf-001',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses mengekstrak teks PDF native',
                'trace_id' => 'trace-pdf-001',
                'data' => [
                    'text' => 'ISI PDF ASLI',
                    'has_text' => true,
                ],
            ]);

        Http::assertSent(function (Request $request) {
            return str_ends_with($request->url(), '/api/pdf/extract-native')
                && (($request->header('X-Trace-Id')[0] ?? null) === 'trace-web-pdf-001');
        });
    }

    public function test_ai_pdf_extract_native_rejects_non_pdf_file(): void
    {
        $user = $this->authenticatedUser('guru');
        Sanctum::actingAs($user);

        $imageFile = UploadedFile::fake()->create('bukan-pdf.png', 100, 'image/png');

        $this->post('/api/v1/ai/pdf/extract-native', [
            'file' => $imageFile,
        ], [
            'Accept' => 'application/json',
        ])->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors(['file']);
    }
}
