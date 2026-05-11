<?php

namespace Tests\Feature\Concerns;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\Cabinet;
use App\Models\Event;
use App\Models\Rack;
use App\Models\Subcategory;
use App\Models\User;

trait CreatesApiFixtures
{
    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'subject' => 'Kearsipan',
            'position' => 'Guru',
            'username' => 'testuser_'.fake()->unique()->numerify('###'),
            'password' => 'Password123',
            'role' => 'guru',
        ], $attributes));
    }

    protected function createArchiveDependencies(array $userAttributes = []): array
    {
        $user = $this->createUser($userAttributes);
        $category = ArchiveCategory::create([
            'name' => 'Kategori '.fake()->unique()->word(),
            'description' => 'Kategori test',
        ]);
        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Subkategori '.fake()->unique()->word(),
        ]);
        $event = Event::create([
            'title' => 'Event '.fake()->unique()->word(),
            'user_id' => $user->id,
            'description' => 'Event test',
            'date' => now()->toDateString(),
            'status' => 'ongoing',
        ]);
        $cabinet = Cabinet::create([
            'cabinet_number' => random_int(1000, 9999),
            'name' => 'Lemari '.fake()->unique()->lexify('???'),
        ]);
        $rack = Rack::create([
            'cabinet_id' => $cabinet->id,
            'rack_number' => 1,
            'capacity' => 20,
        ]);

        return compact('user', 'category', 'subcategory', 'event', 'cabinet', 'rack');
    }

    protected function createArchive(array $attributes = []): Archive
    {
        $dependencies = $this->createArchiveDependencies();

        return Archive::create(array_merge([
            'title' => 'Arsip '.fake()->unique()->word(),
            'year' => 2026,
            'notes' => 'Catatan arsip test',
            'event_id' => $dependencies['event']->id,
            'category_id' => $dependencies['category']->id,
            'subcategory_id' => $dependencies['subcategory']->id,
        ], $attributes));
    }
}
