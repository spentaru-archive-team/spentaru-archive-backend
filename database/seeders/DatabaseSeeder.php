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
        if (env('APP_ENV') == 'local') {
            $this->call([
                UserSeeder::class,
                RackSeeder::class,
                EventSeeder::class,
                ArchiveStorageRuleSeeder::class,
                ArchiveSeeder::class,
                ArchiveFileSeeder::class,
                ArchivePhysicalLocationSeeder::class,
                ArchiveCategorySeeder::class,
                SubcategorySeeder::class,
                CabinetSeeder::class,
            ]);
        } else {
            $this->call([
                ProductionUserSeeder::class,
                RackSeeder::class,
                ArchiveStorageRuleSeeder::class,
                ArchivePhysicalLocationSeeder::class,
                ArchiveCategorySeeder::class,
                SubcategorySeeder::class,
                CabinetSeeder::class,
            ]);
        }
    }
}
