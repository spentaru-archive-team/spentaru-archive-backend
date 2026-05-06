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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArchivePhysicalLocationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'subject' => 'Kearsipan',
            'position' => 'Guru',
            'username' => 'user_'.fake()->unique()->numerify('###'),
            'password' => 'Password123',
            'role' => 'guru',
        ], $attributes));
    }

    private function createArchiveRecord(array $attributes = []): Archive
    {
        $uploader = $attributes['uploader_model'] ?? $this->createUser();
        $eventOwner = $attributes['event_user_model'] ?? $this->createUser([
            'username' => 'event_owner_'.fake()->unique()->numerify('###'),
        ]);
        $category = $attributes['category_model'] ?? ArchiveCategory::create([
            'name' => 'Kategori '.fake()->unique()->word(),
            'description' => 'Kategori test',
            'has_subcategory' => false,
        ]);
        $subcategory = $attributes['subcategory_model'] ?? Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Subkategori '.fake()->unique()->word(),
        ]);
        $event = $attributes['event_model'] ?? Event::create([
            'title' => 'Event '.fake()->unique()->word(),
            'user_id' => $eventOwner->id,
            'description' => 'Event test',
            'date' => now()->toDateString(),
            'status' => 'ongoing',
        ]);

        return Archive::create([
            'title' => $attributes['title'] ?? 'Arsip '.fake()->unique()->word(),
            'year' => $attributes['year'] ?? 2026,
            'notes' => $attributes['notes'] ?? 'Catatan arsip test',
            'event_id' => $attributes['event_id'] ?? $event->id,
            'category_id' => $attributes['category_id'] ?? $category->id,
            'subcategory_id' => array_key_exists('subcategory_id', $attributes) ? $attributes['subcategory_id'] : $subcategory->id,
            'uploader' => $attributes['uploader'] ?? $uploader->id,
            'retention_due_date' => $attributes['retention_due_date'] ?? '2036-01-01',
            'retention_status' => $attributes['retention_status'] ?? 'active',
        ]);
    }

    private function createCabinetWithRack(array $cabinetAttributes = [], array $rackAttributes = []): array
    {
        $cabinet = Cabinet::create(array_merge([
            'cabinet_number' => fake()->unique()->numberBetween(10, 99),
            'name' => 'Lemari '.fake()->unique()->word(),
        ], $cabinetAttributes));

        $rack = Rack::create(array_merge([
            'cabinet_id' => $cabinet->id,
            'rack_number' => 1,
            'capacity' => 10,
            'used_capacity' => 0,
        ], $rackAttributes));

        return [$cabinet, $rack];
    }

    public function test_index_returns_physical_locations_for_authenticated_user(): void
    {
        $actor = $this->createUser([
            'role' => 'guru',
            'username' => 'physical_index_actor',
        ]);
        Sanctum::actingAs($actor);

        $archive = $this->createArchiveRecord([
            'uploader' => $actor->id,
            'uploader_model' => $actor,
        ]);
        [$cabinet, $rack] = $this->createCabinetWithRack([
            'cabinet_number' => 1,
            'name' => 'Lemari Utama',
        ], [
            'rack_number' => 2,
            'used_capacity' => 1,
        ]);

        $archive->physicalLocation()->create([
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 4,
            'label_code' => 'L1-R2-S4',
            'notes' => 'Baris depan',
        ]);

        $this->getJson('/api/v1/archives/physical-locations?q=L1-R2')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses menampilkan semua physical location dari arsip',
            ])
            ->assertJsonPath('data.data.0.archive_id', $archive->id)
            ->assertJsonPath('data.data.0.label_code', 'L1-R2-S4');
    }

    public function test_show_returns_404_when_archive_has_no_physical_location(): void
    {
        $actor = $this->createUser([
            'role' => 'guru',
            'username' => 'physical_show_actor',
        ]);
        Sanctum::actingAs($actor);

        $archive = $this->createArchiveRecord([
            'uploader' => $actor->id,
            'uploader_model' => $actor,
        ]);

        $this->getJson("/api/v1/archives/{$archive->id}/physical-locations")
            ->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Physical location tidak ditemukan',
            ]);
    }

    public function test_store_creates_physical_location_and_increments_used_capacity(): void
    {
        $actor = $this->createUser([
            'role' => 'guru',
            'username' => 'physical_store_actor',
        ]);
        Sanctum::actingAs($actor);

        $archive = $this->createArchiveRecord([
            'uploader' => $actor->id,
            'uploader_model' => $actor,
        ]);
        [$cabinet, $rack] = $this->createCabinetWithRack([
            'cabinet_number' => 3,
        ], [
            'rack_number' => 5,
            'capacity' => 6,
            'used_capacity' => 0,
        ]);

        $this->postJson("/api/v1/archives/{$archive->id}/physical-locations", [
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 2,
            'notes_physical_location' => 'Rak tengah',
        ])->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses menyimpan physical location archive',
            ])
            ->assertJsonPath('data.label_code', 'L3-R5-S2')
            ->assertJsonPath('data.notes', 'Rak tengah');

        $this->assertDatabaseHas('archive_physical_locations', [
            'archive_id' => $archive->id,
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 2,
            'label_code' => 'L3-R5-S2',
        ]);
        $this->assertSame(1, $rack->fresh()->used_capacity);
    }

    public function test_store_rejects_rack_that_does_not_belong_to_selected_cabinet(): void
    {
        $actor = $this->createUser([
            'role' => 'guru',
            'username' => 'physical_store_mismatch_actor',
        ]);
        Sanctum::actingAs($actor);

        $archive = $this->createArchiveRecord([
            'uploader' => $actor->id,
            'uploader_model' => $actor,
        ]);
        [$cabinetA] = $this->createCabinetWithRack([
            'cabinet_number' => 11,
        ]);
        [, $rackB] = $this->createCabinetWithRack([
            'cabinet_number' => 22,
        ]);

        $this->postJson("/api/v1/archives/{$archive->id}/physical-locations", [
            'cabinet_id' => $cabinetA->id,
            'rack_id' => $rackB->id,
            'slot_number' => 1,
        ])->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Rak tidak berada di lemari yang dipilih',
            ]);
    }

    public function test_store_rejects_duplicate_slot_in_same_rack(): void
    {
        $actor = $this->createUser([
            'role' => 'guru',
            'username' => 'physical_store_duplicate_actor',
        ]);
        Sanctum::actingAs($actor);

        [$cabinet, $rack] = $this->createCabinetWithRack([
            'cabinet_number' => 4,
        ], [
            'rack_number' => 2,
            'used_capacity' => 1,
        ]);

        $existingArchive = $this->createArchiveRecord();
        $existingArchive->physicalLocation()->create([
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 3,
            'label_code' => 'L4-R2-S3',
        ]);

        $targetArchive = $this->createArchiveRecord([
            'uploader' => $actor->id,
            'uploader_model' => $actor,
        ]);

        $this->postJson("/api/v1/archives/{$targetArchive->id}/physical-locations", [
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 3,
        ])->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Slot pada rak tersebut sudah terpakai',
            ]);
    }

    public function test_update_moves_location_to_new_rack_and_syncs_capacity(): void
    {
        $actor = $this->createUser([
            'role' => 'guru',
            'username' => 'physical_update_actor',
        ]);
        Sanctum::actingAs($actor);

        $archive = $this->createArchiveRecord([
            'uploader' => $actor->id,
            'uploader_model' => $actor,
        ]);
        [$oldCabinet, $oldRack] = $this->createCabinetWithRack([
            'cabinet_number' => 7,
        ], [
            'rack_number' => 1,
            'used_capacity' => 1,
        ]);
        [$newCabinet, $newRack] = $this->createCabinetWithRack([
            'cabinet_number' => 8,
        ], [
            'rack_number' => 4,
            'used_capacity' => 0,
        ]);

        $archive->physicalLocation()->create([
            'cabinet_id' => $oldCabinet->id,
            'rack_id' => $oldRack->id,
            'slot_number' => 1,
            'label_code' => 'L7-R1-S1',
            'notes' => 'Lokasi awal',
        ]);

        $this->putJson("/api/v1/archives/{$archive->id}/physical-locations", [
            'cabinet_id' => $newCabinet->id,
            'rack_id' => $newRack->id,
            'slot_number' => 2,
            'notes_physical_location' => 'Lokasi baru',
        ])->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses memperbarui physical location archive',
            ])
            ->assertJsonPath('data.label_code', 'L8-R4-S2')
            ->assertJsonPath('data.notes', 'Lokasi baru');

        $this->assertSame(0, $oldRack->fresh()->used_capacity);
        $this->assertSame(1, $newRack->fresh()->used_capacity);
    }

    public function test_update_rejects_duplicate_slot_on_same_rack(): void
    {
        $actor = $this->createUser([
            'role' => 'guru',
            'username' => 'physical_update_duplicate_actor',
        ]);
        Sanctum::actingAs($actor);

        [$cabinet, $rack] = $this->createCabinetWithRack([
            'cabinet_number' => 9,
        ], [
            'rack_number' => 3,
            'used_capacity' => 2,
        ]);

        $firstArchive = $this->createArchiveRecord();
        $firstArchive->physicalLocation()->create([
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 2,
            'label_code' => 'L9-R3-S2',
        ]);

        $secondArchive = $this->createArchiveRecord([
            'uploader' => $actor->id,
            'uploader_model' => $actor,
        ]);
        $secondArchive->physicalLocation()->create([
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 1,
            'label_code' => 'L9-R3-S1',
        ]);

        $this->putJson("/api/v1/archives/{$secondArchive->id}/physical-locations", [
            'slot_number' => 2,
        ])->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Slot pada rak tersebut sudah terpakai',
            ]);
    }

    public function test_destroy_requires_admin_and_admin_delete_decrements_capacity(): void
    {
        $guru = $this->createUser([
            'role' => 'guru',
            'username' => 'physical_destroy_guru',
        ]);

        $archive = $this->createArchiveRecord([
            'uploader' => $guru->id,
            'uploader_model' => $guru,
        ]);
        [$cabinet, $rack] = $this->createCabinetWithRack([
            'cabinet_number' => 6,
        ], [
            'rack_number' => 2,
            'used_capacity' => 1,
        ]);
        $archive->physicalLocation()->create([
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => 1,
            'label_code' => 'L6-R2-S1',
        ]);

        Sanctum::actingAs($guru);
        $this->deleteJson("/api/v1/archives/{$archive->id}/physical-locations")
            ->assertStatus(403);

        $admin = $this->createUser([
            'role' => 'admin',
            'username' => 'physical_destroy_admin',
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/archives/{$archive->id}/physical-locations")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses menghapus physical location archive',
            ]);

        $this->assertDatabaseMissing('archive_physical_locations', [
            'archive_id' => $archive->id,
        ]);
        $this->assertSame(0, $rack->fresh()->used_capacity);
    }
}
