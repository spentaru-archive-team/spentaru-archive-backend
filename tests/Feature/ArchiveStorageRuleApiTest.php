<?php

namespace Tests\Feature;

use App\Models\ArchiveCategory;
use App\Models\ArchiveStorageRule;
use App\Models\Cabinet;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArchiveStorageRuleApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'subject' => 'Kearsipan',
            'position' => 'Admin',
            'username' => 'admin_'.fake()->unique()->numerify('###'),
            'password' => 'Password123',
            'role' => 'admin',
        ]);
    }

    private function createCabinet(array $attributes = []): Cabinet
    {
        return Cabinet::create(array_merge([
            'cabinet_number' => fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Lemari '.fake()->unique()->lexify('???'),
        ], $attributes));
    }

    public function test_store_rejects_duplicate_priority_for_same_category_and_subcategory(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $category = ArchiveCategory::create([
            'name' => 'Kategori Storage',
            'description' => 'Kategori test',
            'has_subcategory' => true,
        ]);
        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Subkategori Storage',
        ]);

        ArchiveStorageRule::create([
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $this->createCabinet()->id,
            'priority' => 1,
        ]);

        $response = $this->postJson('/api/v1/archive-storage-rules', [
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $this->createCabinet()->id,
            'priority' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_store_allows_same_category_and_subcategory_with_different_priority(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $category = ArchiveCategory::create([
            'name' => 'Kategori Priority',
            'description' => 'Kategori test',
            'has_subcategory' => true,
        ]);
        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Subkategori Priority',
        ]);

        ArchiveStorageRule::create([
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $this->createCabinet()->id,
            'priority' => 1,
        ]);

        $response = $this->postJson('/api/v1/archive-storage-rules', [
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $this->createCabinet()->id,
            'priority' => 2,
        ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses menambahkan peraturan penyimpanan arsip',
                'data' => [
                    'category_id' => $category->id,
                    'subcategory_id' => $subcategory->id,
                    'priority' => 2,
                ],
            ]);
    }

    public function test_store_allows_same_category_and_priority_for_different_subcategory(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $category = ArchiveCategory::create([
            'name' => 'Kategori Subcategory',
            'description' => 'Kategori test',
            'has_subcategory' => true,
        ]);
        $firstSubcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Subkategori A',
        ]);
        $secondSubcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Subkategori B',
        ]);

        ArchiveStorageRule::create([
            'category_id' => $category->id,
            'subcategory_id' => $firstSubcategory->id,
            'cabinet_id' => $this->createCabinet()->id,
            'priority' => 1,
        ]);

        $response = $this->postJson('/api/v1/archive-storage-rules', [
            'category_id' => $category->id,
            'subcategory_id' => $secondSubcategory->id,
            'cabinet_id' => $this->createCabinet()->id,
            'priority' => 1,
        ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses menambahkan peraturan penyimpanan arsip',
                'data' => [
                    'category_id' => $category->id,
                    'subcategory_id' => $secondSubcategory->id,
                    'priority' => 1,
                ],
            ]);
    }

    public function test_update_rejects_duplicate_priority_for_same_category_and_subcategory(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $category = ArchiveCategory::create([
            'name' => 'Kategori Update',
            'description' => 'Kategori test',
            'has_subcategory' => true,
        ]);
        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Subkategori Update',
        ]);

        ArchiveStorageRule::create([
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $this->createCabinet()->id,
            'priority' => 1,
        ]);
        $target = ArchiveStorageRule::create([
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'cabinet_id' => $this->createCabinet()->id,
            'priority' => 2,
        ]);

        $response = $this->patchJson("/api/v1/archive-storage-rules/{$target->id}", [
            'priority' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors(['priority']);
    }
}
