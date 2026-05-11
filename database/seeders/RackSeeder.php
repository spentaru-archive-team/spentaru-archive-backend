<?php

namespace Database\Seeders;

use App\Models\Cabinet;
use App\Models\Rack;
use Illuminate\Database\Seeder;

class RackSeeder extends Seeder
{
    public function run(): void
    {
        $capacities = [18, 20, 24, 30];

        foreach (Cabinet::query()->orderBy('cabinet_number')->get() as $cabinet) {
            for ($rackNumber = 1; $rackNumber <= 4; $rackNumber++) {
                Rack::query()->updateOrCreate(
                    [
                        'cabinet_id' => $cabinet->id,
                        'rack_number' => $rackNumber,
                    ],
                    [
                        'capacity' => $capacities[($cabinet->cabinet_number + $rackNumber - 2) % count($capacities)],
                        'used_capacity' => 0,
                    ]
                );
            }
        }
    }
}
