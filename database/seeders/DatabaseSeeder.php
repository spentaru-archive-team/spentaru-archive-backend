<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ArchiveCategorySeeder::class,
            SubcategorySeeder::class,
            CabinetSeeder::class,
            RackSeeder::class,
            EventSeeder::class,
            ArchiveStorageRuleSeeder::class,
            ArchiveSeeder::class,
            ArchiveFileSeeder::class,
            ArchivePhysicalLocationSeeder::class,
            OcrTextSeeder::class,
        ]);
    }
}
