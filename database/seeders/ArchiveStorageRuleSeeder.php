<?php

namespace Database\Seeders;

use App\Models\ArchiveCategory;
use App\Models\ArchiveStorageRule;
use App\Models\Cabinet;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class ArchiveStorageRuleSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = ArchiveCategory::query()->pluck('id', 'name');
        $subcategoryIds = Subcategory::query()->pluck('id', 'name');
        $cabinetIds = Cabinet::query()->pluck('id', 'cabinet_number');

        $items = [
            [
                'category_name' => 'Data Siswa',
                'subcategory_name' => 'Data Pribadi',
                'cabinet_number' => 1,
                'priority' => 1,
            ],
            [
                'category_name' => 'Data Siswa',
                'subcategory_name' => 'Nilai',
                'cabinet_number' => 2,
                'priority' => 2,
            ],
            [
                'category_name' => 'Data Guru dan Staf',
                'subcategory_name' => null,
                'cabinet_number' => 3,
                'priority' => 1,
            ],
            [
                'category_name' => 'Akademik/Kurikulum',
                'subcategory_name' => 'Modul Ajar / RPP',
                'cabinet_number' => 4,
                'priority' => 1,
            ],
            [
                'category_name' => 'Administrasi Sekolah dan Bendahara',
                'subcategory_name' => 'Surat Masuk',
                'cabinet_number' => 6,
                'priority' => 2,
            ],
            [
                'category_name' => 'Administrasi Sekolah dan Bendahara',
                'subcategory_name' => null,
                'cabinet_number' => 7,
                'priority' => 3,
            ],
            [
                'category_name' => 'Inventaris Sekolah',
                'subcategory_name' => null,
                'cabinet_number' => 5,
                'priority' => 1,
            ],
            [
                'category_name' => 'Kesiswaan dan BK',
                'subcategory_name' => 'Bimbingan Konseling',
                'cabinet_number' => 8,
                'priority' => 2,
            ],
            [
                'category_name' => 'Program Literasi',
                'subcategory_name' => null,
                'cabinet_number' => 9,
                'priority' => 4,
            ],
        ];

        foreach ($items as $item) {
            $categoryId = $categoryIds[$item['category_name']] ?? null;
            $cabinetId = $cabinetIds[$item['cabinet_number']] ?? null;

            if (! $categoryId || ! $cabinetId) {
                continue;
            }

            ArchiveStorageRule::query()->updateOrCreate(
                [
                    'category_id' => $categoryId,
                    'subcategory_id' => $item['subcategory_name']
                        ? ($subcategoryIds[$item['subcategory_name']] ?? null)
                        : null,
                    'cabinet_id' => $cabinetId,
                    'priority' => $item['priority'],
                ],
                []
            );
        }
    }
}
