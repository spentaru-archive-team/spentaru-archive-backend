<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\Event;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArchiveApiTest extends TestCase
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
            'retention_decided_at' => $attributes['retention_decided_at'] ?? null,
            'retention_decided_by' => $attributes['retention_decided_by'] ?? null,
            'retention_note' => $attributes['retention_note'] ?? null,
        ]);
    }

    public function test_index_filters_archives_by_direct_fields(): void
    {
        $actor = $this->createUser([
            'role' => 'admin',
            'username' => 'archive_filter_actor',
        ]);
        Sanctum::actingAs($actor);

        $matched = $this->createArchiveRecord([
            'title' => 'Rapor Semester Genap',
            'retention_status' => 'active',
        ]);

        $this->createArchiveRecord([
            'title' => 'Notulen Rapat',
            'retention_status' => 'active',
        ]);

        $this->createArchiveRecord([
            'title' => 'Rapor Semester Ganjil',
            'retention_status' => 'retained',
        ]);

        $response = $this->getJson('/api/v1/archives?filters[title][$contains]=Rapor&filters[retention_status][$eq]=active');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses mengambil archive',
            ])
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $matched->id)
            ->assertJsonPath('data.data.0.title', 'Rapor Semester Genap');
    }

    public function test_index_supports_boolean_or_filters_for_direct_fields(): void
    {
        $actor = $this->createUser([
            'role' => 'admin',
            'username' => 'archive_or_filter_actor',
        ]);
        Sanctum::actingAs($actor);

        $first = $this->createArchiveRecord([
            'title' => 'Arsip Active',
            'retention_status' => 'active',
        ]);
        $second = $this->createArchiveRecord([
            'title' => 'Arsip Retained',
            'retention_status' => 'retained',
        ]);
        $this->createArchiveRecord([
            'title' => 'Arsip Destroyed',
            'retention_status' => 'destroyed',
        ]);

        $response = $this->getJson('/api/v1/archives?filters[$or][0][retention_status][$eq]=active&filters[$or][1][retention_status][$eq]=retained');

        $response->assertOk()
            ->assertJsonCount(2, 'data.data');

        $this->assertSame(
            [$first->id, $second->id],
            collect($response->json('data.data'))->pluck('id')->sort()->values()->all()
        );
    }

    public function test_index_sorts_archives_by_direct_and_related_fields(): void
    {
        $actor = $this->createUser([
            'role' => 'admin',
            'username' => 'archive_sort_actor',
        ]);
        Sanctum::actingAs($actor);

        $categoryZ = ArchiveCategory::create([
            'name' => 'Zeta',
            'description' => 'Kategori Z',
            'has_subcategory' => false,
        ]);
        $subcategoryZ = Subcategory::create([
            'category_id' => $categoryZ->id,
            'name' => 'Sub Zeta',
        ]);

        $categoryA = ArchiveCategory::create([
            'name' => 'Alpha',
            'description' => 'Kategori A',
            'has_subcategory' => false,
        ]);
        $subcategoryA = Subcategory::create([
            'category_id' => $categoryA->id,
            'name' => 'Sub Alpha',
        ]);

        $olderArchive = $this->createArchiveRecord([
            'title' => 'Arsip Tahun Lama',
            'year' => 2022,
            'category_model' => $categoryZ,
            'subcategory_model' => $subcategoryZ,
        ]);
        $newerArchive = $this->createArchiveRecord([
            'title' => 'Arsip Tahun Baru',
            'year' => 2025,
            'category_model' => $categoryA,
            'subcategory_model' => $subcategoryA,
        ]);

        $directSortResponse = $this->getJson('/api/v1/archives?sort=year:desc');
        $this->assertSame(
            [$newerArchive->id, $olderArchive->id],
            collect($directSortResponse->json('data.data'))->pluck('id')->all()
        );

        $relationSortResponse = $this->getJson('/api/v1/archives?sort=category.name:asc');
        $this->assertSame(
            [$newerArchive->id, $olderArchive->id],
            collect($relationSortResponse->json('data.data'))->pluck('id')->all()
        );
    }
}
