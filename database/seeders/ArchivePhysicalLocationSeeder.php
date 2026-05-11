<?php

namespace Database\Seeders;

use App\Models\Archive;
use App\Models\Rack;
use Illuminate\Database\Seeder;

class ArchivePhysicalLocationSeeder extends Seeder
{
    public function run(): void
    {
        $racks = Rack::query()->with('cabinet')->orderBy('cabinet_id')->orderBy('rack_number')->get()->values();
        $archivesWithFiles = Archive::query()
            ->has('files')
            ->orderBy('id')
            ->get()
            ->values();

        $locationIndex = 0;

        foreach (Archive::query()->doesntHave('files')->get() as $archive) {
            $archive->physicalLocation()->delete();
        }

        foreach ($archivesWithFiles as $index => $archive) {
            if ($index % 4 === 3 || $racks->isEmpty()) {
                $archive->physicalLocation()->delete();

                continue;
            }

            $rack = $racks[$locationIndex % $racks->count()];
            $slotNumber = intdiv($locationIndex, $racks->count()) + 1;

            $archive->physicalLocation()->updateOrCreate(
                ['archive_id' => $archive->id],
                [
                    'cabinet_id' => $rack->cabinet_id,
                    'rack_id' => $rack->id,
                    'slot_number' => $slotNumber,
                    'label_code' => sprintf(
                        'C%02d-R%02d-S%02d',
                        $rack->cabinet->cabinet_number,
                        $rack->rack_number,
                        $slotNumber
                    ),
                    'notes' => $index % 5 === 0 ? null : 'Penyimpanan fisik aktif di lemari arsip.',
                ]
            );

            $locationIndex++;
        }

        foreach (Rack::query()->withCount('physicalLocations')->get() as $rack) {
            $rack->update([
                'used_capacity' => $rack->physical_locations_count,
            ]);
        }
    }
}
