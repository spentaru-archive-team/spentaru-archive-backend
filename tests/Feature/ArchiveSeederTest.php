<?php

namespace Tests\Feature;

use App\Models\ArchivePhysicalLocation;
use App\Models\Rack;
use App\Models\User;
use Database\Seeders\ArchiveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArchiveSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_seeder_is_idempotent_and_syncs_storage_data(): void
    {
        $this->seed(ArchiveSeeder::class);
        $this->seed(ArchiveSeeder::class);

        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->assertSame(1, User::query()->where('username', 'admin')->count());
        $this->assertSame(1, User::query()->where('username', 'guru_demo')->count());
        $this->assertTrue(Hash::check('password', $admin->password));

        ArchivePhysicalLocation::query()
            ->with('rack')
            ->get()
            ->each(function (ArchivePhysicalLocation $location): void {
                $expectedLabel = sprintf(
                    'L%d-R%d-S%02d',
                    $location->cabinet_id,
                    $location->rack->rack_number,
                    $location->slot_number
                );

                $this->assertSame($expectedLabel, $location->label_code);
            });

        Rack::query()
            ->withCount('physicalLocations')
            ->get()
            ->each(function (Rack $rack): void {
                $this->assertSame($rack->physical_locations_count, $rack->used_capacity);
            });
    }
}
