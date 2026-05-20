<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveFile;
use App\Models\ArchivePhysicalLocation;
use App\Models\ArchiveStorageRule;
use App\Models\Cabinet;
use App\Models\Event;
use App\Models\Rack;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function user(string $role = 'guru', array $overrides = []): User
    {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'name' => ucfirst($role).' User '.$counter,
            'subject' => 'Matematika',
            'position' => 'Guru',
            'username' => $role.'_'.$counter,
            'password' => Hash::make('Password1'),
            'role' => $role,
        ], $overrides));
    }

    private function admin(array $overrides = []): User
    {
        return $this->user('admin', $overrides);
    }

    private function actingAsRole(string $role = 'guru'): User
    {
        $user = $role === 'admin' ? $this->admin() : $this->user($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function category(array $overrides = []): ArchiveCategory
    {
        static $counter = 0;
        $counter++;

        return ArchiveCategory::create(array_merge([
            'name' => 'Kategori '.$counter,
            'description' => 'Deskripsi kategori '.$counter,
            'has_subcategory' => false,
        ], $overrides));
    }

    private function subcategory(ArchiveCategory $category, array $overrides = []): Subcategory
    {
        static $counter = 0;
        $counter++;

        $category->update(['has_subcategory' => true]);

        return Subcategory::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Subkategori '.$counter,
        ], $overrides));
    }

    private function event(User $user, array $overrides = []): Event
    {
        static $counter = 0;
        $counter++;

        return Event::create(array_merge([
            'title' => 'Event '.$counter,
            'user_id' => $user->id,
            'description' => 'Deskripsi event '.$counter,
            'date' => '2026-05-20',
            'status' => 'ongoing',
        ], $overrides));
    }

    private function cabinet(array $overrides = []): Cabinet
    {
        static $counter = 0;
        $counter++;

        return Cabinet::create(array_merge([
            'cabinet_number' => $counter,
            'name' => 'Lemari '.$counter,
        ], $overrides));
    }

    private function rack(Cabinet $cabinet, array $overrides = []): Rack
    {
        static $counter = 0;
        $counter++;

        return Rack::create(array_merge([
            'cabinet_id' => $cabinet->id,
            'rack_number' => $counter,
            'capacity' => 5,
            'used_capacity' => 0,
        ], $overrides));
    }

    private function archive(array $overrides = []): Archive
    {
        $uploader = $overrides['uploader_model'] ?? $this->user();
        unset($overrides['uploader_model']);

        $category = $overrides['category_model'] ?? $this->category();
        unset($overrides['category_model']);

        $event = $overrides['event_model'] ?? $this->event($uploader);
        unset($overrides['event_model']);

        static $counter = 0;
        $counter++;

        return Archive::create(array_merge([
            'event_id' => $event->id,
            'title' => 'Arsip '.$counter,
            'year' => 2026,
            'notes' => 'Catatan arsip '.$counter,
            'category_id' => $category->id,
            'subcategory_id' => null,
            'uploader' => $uploader->id,
            'retention_due_date' => now()->addYear()->toDateString(),
            'retention_status' => 'active',
        ], $overrides));
    }

    private function archiveWithFile(array $overrides = []): Archive
    {
        Storage::fake('local');

        $archive = $this->archive($overrides);
        Storage::disk('local')->put('uploads/test.pdf', 'PDF content');
        ArchiveFile::create([
            'archive_id' => $archive->id,
            'file_name' => 'test.pdf',
            'file_size' => 11,
            'file_type' => 'pdf',
            'vector_id' => (string) Str::uuid(),
            'extraction_status' => 'done',
        ]);

        return $archive->fresh('files');
    }

    public function test_auth_login_token_login_me_and_logout_contracts(): void
    {
        $user = $this->user('admin', ['username' => 'admin_login']);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_login',
            'password' => 'salah',
        ])->assertStatus(401)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_login',
            'password' => 'Password1',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.username', 'admin_login');

        $this->postJson('/api/v1/auth/token-login', [
            'username' => 'admin_login',
            'password' => 'Password1',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['token']]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'admin_login');

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_auth_validation_and_protected_endpoint_reject_guest(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['errors' => ['username', 'password']]);

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_admin_only_routes_reject_guru_and_allow_admin_user_crud(): void
    {
        $guru = $this->actingAsRole('guru');

        $this->getJson('/api/v1/users')
            ->assertStatus(403);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/users', [
            'name' => 'Guru Baru',
            'subject' => 'IPA',
            'position' => 'Wali Kelas',
            'username' => 'guru_baru',
            'password' => 'Password1',
            'role' => 'guru',
        ])->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.username', 'guru_baru');

        $created = User::where('username', 'guru_baru')->firstOrFail();

        $this->getJson('/api/v1/users?q=guru_baru')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/users/'.$created->id)
            ->assertOk()
            ->assertJsonPath('data.username', 'guru_baru');

        $this->putJson('/api/v1/users/'.$created->id, [
            'name' => 'Guru Update',
            'subject' => 'IPS',
            'position' => 'Staf',
            'username' => 'guru_update',
            'role' => 'admin',
        ])->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'username' => 'guru_update',
            'role' => 'admin',
        ]);

        $this->putJson('/api/v1/users/'.$created->id.'/reset-password', [
            'password' => 'Password2',
        ])->assertOk();

        $this->deleteJson('/api/v1/users/'.$guru->id)
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_user_validation_self_update_and_self_delete_guard(): void
    {
        $admin = $this->actingAsRole('admin');

        $this->postJson('/api/v1/users', [
            'name' => 'Invalid',
            'subject' => 'IPA',
            'position' => 'Guru',
            'username' => 'invalid_user',
            'password' => 'password',
            'role' => 'guru',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->putJson('/api/v1/users/me', [
            'name' => 'Admin Rename',
            'username' => 'admin_rename',
            'password' => 'Password9',
        ])->assertOk()
            ->assertJsonPath('data.username', 'admin_rename');

        $this->deleteJson('/api/v1/users/'.$admin->id)
            ->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    public function test_category_endpoints_cover_crud_nested_subcategories_validation_and_delete_guard(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/categories', [
            'name' => 'Dokumen Akademik',
            'description' => 'Kategori akademik',
            'subcategories' => [
                ['name' => 'Rapor'],
                ['name' => 'Ijazah'],
            ],
        ])->assertCreated()
            ->assertJsonPath('status', 'success');

        $category = ArchiveCategory::where('name', 'Dokumen Akademik')->firstOrFail();

        $this->postJson('/api/v1/categories', [
            'name' => 'Dokumen Akademik',
        ])->assertStatus(422);

        $this->getJson('/api/v1/categories?all=1&q=Rapor')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/categories/'.$category->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Dokumen Akademik');

        $subcategory = $category->subcategories()->firstOrFail();
        $this->putJson('/api/v1/categories/'.$category->id, [
            'name' => 'Dokumen Akademik Update',
            'subcategories' => [
                ['id' => $subcategory->id, 'name' => 'Rapor Update'],
                ['name' => 'Transkrip'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('subcategories', ['name' => 'Rapor Update']);
        $this->assertDatabaseHas('subcategories', ['name' => 'Transkrip']);

        $this->archive(['category_model' => $category]);
        $this->deleteJson('/api/v1/categories/'.$category->id)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_subcategory_endpoints_cover_filter_duplicate_update_and_delete_guard(): void
    {
        Sanctum::actingAs($this->admin());

        $category = $this->category();
        $otherCategory = $this->category();

        $this->postJson('/api/v1/subcategories', [
            'category_id' => $category->id,
            'name' => 'Surat Masuk',
        ])->assertCreated()
            ->assertJsonPath('data.category_id', $category->id);

        $subcategory = Subcategory::where('name', 'Surat Masuk')->firstOrFail();

        $this->postJson('/api/v1/subcategories', [
            'category_id' => $category->id,
            'name' => 'Surat Masuk',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->getJson('/api/v1/subcategories?all=1&category_id='.$category->id)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');

        $this->putJson('/api/v1/subcategories/'.$subcategory->id, [
            'category_id' => $otherCategory->id,
            'name' => 'Surat Pindah',
        ])->assertOk()
            ->assertJsonPath('data.category_id', $otherCategory->id);

        $archiveCategory = $this->category(['has_subcategory' => true]);
        $usedSubcategory = $this->subcategory($archiveCategory);
        $this->archive([
            'category_model' => $archiveCategory,
            'category_id' => $archiveCategory->id,
            'subcategory_id' => $usedSubcategory->id,
        ]);

        $this->deleteJson('/api/v1/subcategories/'.$usedSubcategory->id)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_event_endpoints_cover_read_admin_write_pending_uploads_and_delete_guard(): void
    {
        $teacher = $this->user();
        $event = $this->event($teacher, ['title' => 'Rapat Tahunan']);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/events?all=1&q=Rapat')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/events/pending-uploads')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->postJson('/api/v1/events', [
            'title' => 'Tidak boleh',
            'user_id' => $teacher->id,
            'date' => '2026-05-20',
            'status' => 'ongoing',
        ])->assertStatus(403);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/events', [
            'title' => 'Workshop',
            'description' => 'Pelatihan',
            'user_id' => $teacher->id,
            'date' => '2026-05-21',
            'status' => 'ongoing',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Workshop');

        $this->putJson('/api/v1/events/'.$event->id, [
            'status' => 'done',
        ])->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->getJson('/api/v1/events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Rapat Tahunan');

        $this->archive(['event_model' => $event, 'event_id' => $event->id]);
        $this->deleteJson('/api/v1/events/'.$event->id)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_cabinet_endpoints_cover_nested_racks_validation_update_and_delete_guard(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/cabinets', [
            'cabinet_number' => 10,
            'name' => 'Lemari Utama',
            'racks' => [
                ['rack_number' => 1, 'capacity' => 5, 'used_capacity' => 0],
                ['rack_number' => 2, 'capacity' => 6, 'used_capacity' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data.racks');

        $cabinet = Cabinet::where('name', 'Lemari Utama')->firstOrFail();
        $rack = $cabinet->racks()->firstOrFail();

        $this->postJson('/api/v1/cabinets', [
            'cabinet_number' => 10,
            'name' => 'Duplikat',
            'racks' => [
                ['rack_number' => 1, 'capacity' => 1, 'used_capacity' => 2],
            ],
        ])->assertStatus(422);

        $this->getJson('/api/v1/cabinets')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/cabinets/'.$cabinet->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Lemari Utama');

        $this->putJson('/api/v1/cabinets/'.$cabinet->id, [
            'name' => 'Lemari Utama Update',
            'racks' => [
                ['id' => $rack->id, 'rack_number' => 1, 'capacity' => 7, 'used_capacity' => 0],
                ['rack_number' => 3, 'capacity' => 9, 'used_capacity' => 0],
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Lemari Utama Update');

        $archive = $this->archive();
        ArchivePhysicalLocation::create([
            'archive_id' => $archive->id,
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 1,
            'label_code' => 'L10-R1-S1',
        ]);

        $this->deleteJson('/api/v1/cabinets/'.$cabinet->id)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_rack_endpoints_cover_crud_capacity_duplicate_and_occupied_delete_guards(): void
    {
        Sanctum::actingAs($this->admin());

        $cabinet = $this->cabinet();

        $this->postJson('/api/v1/racks', [
            'cabinet_id' => $cabinet->id,
            'rack_number' => 1,
            'capacity' => 5,
            'used_capacity' => 0,
        ])->assertCreated()
            ->assertJsonPath('data.rack_number', 1);

        $rack = Rack::where('cabinet_id', $cabinet->id)->where('rack_number', 1)->firstOrFail();

        $this->postJson('/api/v1/racks', [
            'cabinet_id' => $cabinet->id,
            'rack_number' => 2,
            'capacity' => 1,
            'used_capacity' => 2,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Used capacity tidak boleh lebih besar dari capacity');

        $this->postJson('/api/v1/racks', [
            'cabinet_id' => $cabinet->id,
            'rack_number' => 1,
            'capacity' => 5,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->getJson('/api/v1/racks?cabinet_id='.$cabinet->id)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/racks/'.$rack->id)
            ->assertOk()
            ->assertJsonPath('data.rack_number', 1);

        $this->putJson('/api/v1/racks/'.$rack->id, [
            'capacity' => 8,
            'used_capacity' => 1,
        ])->assertOk()
            ->assertJsonPath('data.capacity', 8);

        $archive = $this->archive();
        ArchivePhysicalLocation::create([
            'archive_id' => $archive->id,
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 1,
            'label_code' => 'L'.$cabinet->cabinet_number.'-R1-S1',
        ]);

        $this->putJson('/api/v1/racks/'.$rack->id, [
            'capacity' => 0,
        ])->assertStatus(422);

        $this->deleteJson('/api/v1/racks/'.$rack->id)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_archive_store_allows_category_without_subcategory_and_rejects_impossible_subcategory_combinations(): void
    {
        Storage::fake('local');
        Http::fake(fn () => Http::response([
            'data' => [
                'text' => 'hasil ocr',
                'vector_id' => (string) Str::uuid(),
            ],
        ], 200));

        $user = $this->actingAsRole('guru');
        $categoryWithoutSubcategory = $this->category(['has_subcategory' => false]);
        $categoryWithSubcategory = $this->category(['has_subcategory' => true]);
        $subcategory = $this->subcategory($categoryWithSubcategory);
        $otherSubcategory = $this->subcategory($this->category(['has_subcategory' => true]));
        $event = $this->event($user);

        $this->postJson('/api/v1/archives', [
            'title' => 'Arsip Tanpa Subkategori',
            'year' => 2026,
            'notes' => 'boleh null subcategory',
            'event_id' => $event->id,
            'category_id' => $categoryWithoutSubcategory->id,
            'file' => UploadedFile::fake()->create('arsip.pdf', 10, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.subcategory_id', null);

        $this->postJson('/api/v1/archives', [])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['errors' => ['title', 'year', 'file', 'category_id']]);

        $this->postJson('/api/v1/archives', [
            'title' => 'Kategori tidak ada',
            'year' => 2026,
            'category_id' => 999999,
            'file' => UploadedFile::fake()->create('arsip.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/archives', [
            'title' => 'Tahun tidak masuk akal',
            'year' => 'dua ribu dua puluh enam',
            'category_id' => $categoryWithoutSubcategory->id,
            'file' => UploadedFile::fake()->create('arsip.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/archives', [
            'title' => 'File executable',
            'year' => 2026,
            'category_id' => $categoryWithoutSubcategory->id,
            'file' => UploadedFile::fake()->create('arsip.exe', 10, 'application/x-msdownload'),
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/archives', [
            'title' => 'Subkategori tidak boleh',
            'year' => 2026,
            'category_id' => $categoryWithoutSubcategory->id,
            'subcategory_id' => $subcategory->id,
            'file' => UploadedFile::fake()->create('arsip.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/archives', [
            'title' => 'Subkategori wajib',
            'year' => 2026,
            'category_id' => $categoryWithSubcategory->id,
            'file' => UploadedFile::fake()->create('arsip.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/archives', [
            'title' => 'Subkategori beda kategori',
            'year' => 2026,
            'category_id' => $categoryWithSubcategory->id,
            'subcategory_id' => $otherSubcategory->id,
            'file' => UploadedFile::fake()->create('arsip.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_archive_endpoints_cover_list_show_update_destroy_file_preview_download_retention_and_missing_file(): void
    {
        Http::fake(fn () => Http::response([
            'data' => [
                'text' => 'ocr update',
                'vector_id' => (string) Str::uuid(),
            ],
        ], 200));

        $admin = $this->actingAsRole('admin');
        $archive = $this->archiveWithFile(['uploader_model' => $admin]);

        $this->getJson('/api/v1/archives?all=1&q=Arsip')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/archives/without-location')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/archives/internal')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthorized AI tool access');

        config(['services.ai_tool.access_key' => 'tool-secret']);
        $this->getJson('/api/v1/archives/internal', ['X-AI-Tool-Key' => 'tool-secret'])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/archives/'.$archive->id)
            ->assertOk()
            ->assertJsonPath('data.title', $archive->title);

        $this->get('/api/v1/archives/'.$archive->id.'/preview')
            ->assertOk();

        $this->get('/api/v1/archives/'.$archive->id.'/download')
            ->assertOk();

        Storage::disk('local')->delete('uploads/test.pdf');
        $this->getJson('/api/v1/archives/'.$archive->id.'/preview')
            ->assertStatus(404)
            ->assertJsonPath('status', 'error');

        Storage::disk('local')->put('uploads/test.pdf', 'PDF content');
        $this->putJson('/api/v1/archives/'.$archive->id, [
            'title' => 'Arsip Update',
            'year' => 2027,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Arsip Update');

        Http::assertSent(function ($request) use ($archive) {
            $payload = $request->data();

            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/api/vector/'.$archive->files->vector_id)
                && in_array(['name' => 'archive_id', 'contents' => (string) $archive->id], $payload, true)
                && in_array(['name' => 'title', 'contents' => 'Arsip Update'], $payload, true)
                && in_array(['name' => 'year', 'contents' => '2027'], $payload, true)
                && ! collect($payload)->contains(fn ($part) => ($part['name'] ?? null) === 'category')
                && ! collect($payload)->contains(fn ($part) => ($part['name'] ?? null) === 'subcategory');
        });

        $this->putJson('/api/v1/archives/'.$archive->id, [
            'title' => '',
            'year' => 'bukan angka',
            'category_id' => 999999,
            'subcategory_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->put('/api/v1/archives/'.$archive->id, [
            'file' => UploadedFile::fake()->create('arsip-baru.pdf', 10, 'application/pdf'),
        ])->assertOk()
            ->assertJsonPath('status', 'success');

        $archive->refresh();
        $this->assertNotSame('test.pdf', $archive->files->file_name);
        $this->assertNotNull($archive->files->vector_id);

        $this->put('/api/v1/archives/'.$archive->id, [
            'file' => UploadedFile::fake()->create('arsip-baru.exe', 10, 'application/x-msdownload'),
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $archive->update(['retention_status' => 'ready_for_destruction']);
        $this->getJson('/api/v1/archives/retention/ready')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->patchJson('/api/v1/archives/'.$archive->id.'/retention/decide', [
            'retention_status' => 'retained',
            'retention_note' => 'ditahan',
        ])->assertOk()
            ->assertJsonPath('data.retention_status', 'retained')
            ->assertJsonPath('data.retention_decided_by', $admin->id);

        $this->patchJson('/api/v1/archives/'.$archive->id.'/retention/decide', [
            'retention_status' => 'tidak_masuk_akal',
        ])->assertStatus(422);

        $this->deleteJson('/api/v1/archives/'.$archive->id)
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_archive_destroy_is_admin_only(): void
    {
        $archive = $this->archive();
        Sanctum::actingAs($this->user('guru'));

        $this->deleteJson('/api/v1/archives/'.$archive->id)
            ->assertStatus(403);
    }

    public function test_archive_physical_location_endpoints_cover_crud_capacity_slot_and_cabinet_mismatch(): void
    {
        Sanctum::actingAs($this->admin());

        $archive = $this->archive();
        $cabinet = $this->cabinet(['cabinet_number' => 30]);
        $rack = $this->rack($cabinet, ['rack_number' => 1, 'capacity' => 2, 'used_capacity' => 0]);
        $otherCabinet = $this->cabinet(['cabinet_number' => 31]);
        $otherRack = $this->rack($otherCabinet, ['rack_number' => 1, 'capacity' => 2, 'used_capacity' => 0]);

        $this->getJson('/api/v1/archives/'.$archive->id.'/physical-locations')
            ->assertStatus(404)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/archives/'.$archive->id.'/physical-locations', [
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 3,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/archives/'.$archive->id.'/physical-locations', [
            'cabinet_id' => $cabinet->id,
            'rack_id' => $otherRack->id,
            'slot_number' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/archives/'.$archive->id.'/physical-locations', [
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 1,
            'notes_physical_location' => 'Rak depan',
        ])->assertCreated()
            ->assertJsonPath('data.label_code', 'L30-R1-S1');

        $this->postJson('/api/v1/archives/'.$archive->id.'/physical-locations', [
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 2,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Physical location archive sudah ada');

        $this->getJson('/api/v1/archives/physical-locations?all=1&q=L30')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/archives/'.$archive->id.'/physical-locations')
            ->assertOk()
            ->assertJsonPath('data.label_code', 'L30-R1-S1');

        $this->putJson('/api/v1/archives/'.$archive->id.'/physical-locations', [
            'slot_number' => 2,
            'notes_physical_location' => 'Rak belakang',
        ])->assertOk()
            ->assertJsonPath('data.label_code', 'L30-R1-S2')
            ->assertJsonPath('data.notes', 'Rak belakang');

        $this->deleteJson('/api/v1/archives/'.$archive->id.'/physical-locations')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_archive_storage_rule_endpoints_cover_crud_subcategory_rules_and_duplicate_priority(): void
    {
        Sanctum::actingAs($this->admin());

        $categoryWithoutSubcategory = $this->category(['has_subcategory' => false]);
        $categoryWithSubcategory = $this->category(['has_subcategory' => true]);
        $subcategory = $this->subcategory($categoryWithSubcategory);
        $cabinet = $this->cabinet();

        $this->postJson('/api/v1/archive-storage-rules', [
            'category_id' => $categoryWithoutSubcategory->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $cabinet->id,
            'priority' => 1,
        ])->assertStatus(422);

        $this->postJson('/api/v1/archive-storage-rules', [
            'category_id' => $categoryWithSubcategory->id,
            'cabinet_id' => $cabinet->id,
            'priority' => 1,
        ])->assertStatus(422);

        $this->postJson('/api/v1/archive-storage-rules', [
            'category_id' => $categoryWithSubcategory->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $cabinet->id,
            'priority' => 1,
        ])->assertCreated()
            ->assertJsonPath('status', 'success');

        $rule = ArchiveStorageRule::where('category_id', $categoryWithSubcategory->id)->firstOrFail();

        $this->postJson('/api/v1/archive-storage-rules', [
            'category_id' => $categoryWithSubcategory->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $cabinet->id,
            'priority' => 1,
        ])->assertStatus(422);

        $this->getJson('/api/v1/archive-storage-rules')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->getJson('/api/v1/archive-storage-rules/'.$rule->id)
            ->assertOk()
            ->assertJsonPath('data.priority', 1);

        $this->patchJson('/api/v1/archive-storage-rules/'.$rule->id, [
            'priority' => 2,
        ])->assertOk()
            ->assertJsonPath('data.priority', 2);

        $this->deleteJson('/api/v1/archive-storage-rules/'.$rule->id)
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_dashboard_endpoints_return_totals_and_teacher_specific_missing_archives(): void
    {
        $teacher = $this->actingAsRole('guru');
        $this->event($teacher, ['title' => 'Belum Upload']);
        $eventWithArchive = $this->event($teacher, ['title' => 'Sudah Upload']);
        $this->archive(['uploader_model' => $teacher, 'event_model' => $eventWithArchive, 'event_id' => $eventWithArchive->id]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total', 1);

        $this->getJson('/api/v1/dashboard/teachers-without-archives')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Belum Upload');
    }

    public function test_ai_chat_proxy_requires_trace_id_passes_through_success_and_handles_tool_auth(): void
    {
        config([
            'services.ai_gateway.base_url' => 'http://ai-service.test',
            'services.ai_tool.access_key' => 'tool-secret',
        ]);

        $this->actingAsRole('guru');

        $this->postJson('/api/v1/chat/ask', [
            'message' => 'halo',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/ai/chat/ask', [
            'message' => 'halo',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        Redis::shouldReceive('get')->once()->andReturn(0);
        Redis::shouldReceive('rpush')->twice()->andReturn(1, 2);
        Redis::shouldReceive('lrange')->once()->andReturn([
            json_encode(['role' => 'user', 'content' => 'cari arsip']),
        ]);
        Redis::shouldReceive('incr')->once()->andReturn(1);

        Http::fake([
            'http://ai-service.test/api/chat/ask' => Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => ['answer' => 'jawaban ai'],
                'trace_id' => 'trace-123',
            ], 200, ['X-Trace-Id' => 'trace-123']),
        ]);

        $this->postJson('/api/v1/chat/ask', [
            'message' => 'cari arsip',
            'use_search' => true,
        ], ['X-Trace-Id' => 'trace-123'])
            ->assertOk()
            ->assertHeader('X-Trace-Id', 'trace-123')
            ->assertJsonPath('data.answer', 'jawaban ai');

    }

    public function test_global_json_errors_cover_not_found_method_not_allowed_and_validation_shape(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/categories/999999')
            ->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Resource tidak ditemukan');

        $this->postJson('/api/v1/categories/999999')
            ->assertStatus(405)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/racks', [
            'cabinet_id' => 999999,
            'rack_number' => 0,
            'capacity' => 0,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['errors']);
    }
}
