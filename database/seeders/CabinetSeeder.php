<?php

namespace Database\Seeders;

use App\Models\Cabinet;
use Illuminate\Database\Seeder;

class CabinetSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['cabinet_number' => 1, 'name' => 'Standar Isi'],
            ['cabinet_number' => 2, 'name' => 'Standar Proses'],
            ['cabinet_number' => 3, 'name' => 'Standar Kompetensi Lulusan'],
            ['cabinet_number' => 4, 'name' => 'Standar Pendidik & Tendik'],
            ['cabinet_number' => 5, 'name' => 'Standar Sarana Prasarana'],
            ['cabinet_number' => 6, 'name' => 'Standar Pengelolaan'],
            ['cabinet_number' => 7, 'name' => 'Standar Pembiayaan'],
            ['cabinet_number' => 8, 'name' => 'Standar Penilaian'],
            ['cabinet_number' => 9, 'name' => 'Campuran'],
            ['cabinet_number' => 10, 'name' => 'Lemari Soal Ujian'],
        ];

        foreach ($items as $item) {
            Cabinet::query()->updateOrCreate(
                ['cabinet_number' => $item['cabinet_number']],
                ['name' => $item['name']]
            );
        }
    }
}
