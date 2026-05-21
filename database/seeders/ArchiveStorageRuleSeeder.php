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
        $subcategoryIds = Subcategory::query()
            ->with('category:id,name')
            ->get()
            ->mapWithKeys(fn (Subcategory $subcategory): array => [
                $subcategory->category->name.'|'.$subcategory->name => $subcategory->id,
            ]);
        $cabinetIds = Cabinet::query()->pluck('id', 'cabinet_number');

        $items = [
            ['category_name' => 'Data Siswa', 'subcategory_name' => null, 'cabinet_number' => 9, 'priority' => 1],
            ['category_name' => 'Data Siswa', 'subcategory_name' => 'Data Pribadi', 'cabinet_number' => 9, 'priority' => 1],
            ['category_name' => 'Data Siswa', 'subcategory_name' => 'Nilai', 'cabinet_number' => 8, 'priority' => 1],
            ['category_name' => 'Data Siswa', 'subcategory_name' => 'Absensi', 'cabinet_number' => 8, 'priority' => 1],
            ['category_name' => 'Data Siswa', 'subcategory_name' => 'Mutasi', 'cabinet_number' => 9, 'priority' => 1],

            ['category_name' => 'Data Guru dan Staf', 'subcategory_name' => null, 'cabinet_number' => 4, 'priority' => 1],
            ['category_name' => 'Data Guru dan Staf', 'subcategory_name' => 'Guru ASN', 'cabinet_number' => 4, 'priority' => 1],
            ['category_name' => 'Data Guru dan Staf', 'subcategory_name' => 'Guru Honorer', 'cabinet_number' => 4, 'priority' => 1],
            ['category_name' => 'Data Guru dan Staf', 'subcategory_name' => 'Tenaga Kependidikan', 'cabinet_number' => 4, 'priority' => 1],

            ['category_name' => 'Akademik/Kurikulum', 'subcategory_name' => null, 'cabinet_number' => 1, 'priority' => 1],
            ['category_name' => 'Akademik/Kurikulum', 'subcategory_name' => 'Jadwal Pelajaran', 'cabinet_number' => 2, 'priority' => 1],
            ['category_name' => 'Akademik/Kurikulum', 'subcategory_name' => 'Modul Ajar / RPP', 'cabinet_number' => 2, 'priority' => 1],
            ['category_name' => 'Akademik/Kurikulum', 'subcategory_name' => 'Kurikulum', 'cabinet_number' => 1, 'priority' => 1],
            ['category_name' => 'Akademik/Kurikulum', 'subcategory_name' => 'Kalender Pendidikan', 'cabinet_number' => 1, 'priority' => 1],
            ['category_name' => 'Akademik/Kurikulum', 'subcategory_name' => 'Tahfidz', 'cabinet_number' => 3, 'priority' => 1],

            ['category_name' => 'Administrasi Sekolah dan Bendahara', 'subcategory_name' => null, 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Administrasi Sekolah dan Bendahara', 'subcategory_name' => 'Surat Masuk', 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Administrasi Sekolah dan Bendahara', 'subcategory_name' => 'Surat Keluar', 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Administrasi Sekolah dan Bendahara', 'subcategory_name' => 'Notulen Rapat', 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Administrasi Sekolah dan Bendahara', 'subcategory_name' => 'SK', 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Administrasi Sekolah dan Bendahara', 'subcategory_name' => 'Laporan Kegiatan Sekolah', 'cabinet_number' => 6, 'priority' => 1],

            ['category_name' => 'Inventaris Sekolah', 'subcategory_name' => null, 'cabinet_number' => 5, 'priority' => 1],
            ['category_name' => 'Inventaris Sekolah', 'subcategory_name' => 'Aset Tetap', 'cabinet_number' => 5, 'priority' => 1],
            ['category_name' => 'Inventaris Sekolah', 'subcategory_name' => 'Laboratorium', 'cabinet_number' => 5, 'priority' => 1],
            ['category_name' => 'Inventaris Sekolah', 'subcategory_name' => 'Perpustakaan', 'cabinet_number' => 5, 'priority' => 1],

            ['category_name' => 'Kesiswaan dan BK', 'subcategory_name' => null, 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Kesiswaan dan BK', 'subcategory_name' => 'OSIS', 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Kesiswaan dan BK', 'subcategory_name' => 'Prestasi Siswa', 'cabinet_number' => 3, 'priority' => 1],
            ['category_name' => 'Kesiswaan dan BK', 'subcategory_name' => 'Bimbingan Konseling', 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Kesiswaan dan BK', 'subcategory_name' => 'Ekstrakurikuler', 'cabinet_number' => 6, 'priority' => 1],

            ['category_name' => 'Arsip Alumni', 'subcategory_name' => null, 'cabinet_number' => 3, 'priority' => 1],
            ['category_name' => 'Arsip Alumni', 'subcategory_name' => 'Tracer Study', 'cabinet_number' => 3, 'priority' => 1],
            ['category_name' => 'Arsip Alumni', 'subcategory_name' => 'Administrasi Alumni', 'cabinet_number' => 3, 'priority' => 1],

            ['category_name' => 'MBG', 'subcategory_name' => null, 'cabinet_number' => 7, 'priority' => 1],
            ['category_name' => 'MBG', 'subcategory_name' => 'Program MBG', 'cabinet_number' => 7, 'priority' => 1],
            ['category_name' => 'MBG', 'subcategory_name' => 'Laporan MBG', 'cabinet_number' => 7, 'priority' => 1],

            ['category_name' => 'Program Literasi', 'subcategory_name' => null, 'cabinet_number' => 1, 'priority' => 1],
            ['category_name' => 'Program Literasi', 'subcategory_name' => 'Pojok Baca', 'cabinet_number' => 5, 'priority' => 1],
            ['category_name' => 'Program Literasi', 'subcategory_name' => 'Jurnal Literasi', 'cabinet_number' => 1, 'priority' => 1],
            ['category_name' => 'Program Literasi', 'subcategory_name' => 'Monitoring Literasi', 'cabinet_number' => 8, 'priority' => 1],

            ['category_name' => 'Pengembang Sekolah', 'subcategory_name' => null, 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Pengembang Sekolah', 'subcategory_name' => 'Peningkatan Mutu', 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'Pengembang Sekolah', 'subcategory_name' => 'Pengembangan Sekolah', 'cabinet_number' => 6, 'priority' => 1],

            ['category_name' => 'Backup / Arsip Dokumen', 'subcategory_name' => null, 'cabinet_number' => 9, 'priority' => 1],
            ['category_name' => 'Backup / Arsip Dokumen', 'subcategory_name' => 'Backup Harian', 'cabinet_number' => 9, 'priority' => 1],
            ['category_name' => 'Backup / Arsip Dokumen', 'subcategory_name' => 'Backup Mingguan', 'cabinet_number' => 9, 'priority' => 1],
            ['category_name' => 'Backup / Arsip Dokumen', 'subcategory_name' => 'Backup Bulanan', 'cabinet_number' => 9, 'priority' => 1],

            ['category_name' => 'Program Adiwiyata', 'subcategory_name' => null, 'cabinet_number' => 5, 'priority' => 1],
            ['category_name' => 'Program Adiwiyata', 'subcategory_name' => 'Program Adiwiyata', 'cabinet_number' => 5, 'priority' => 1],
            ['category_name' => 'Program Adiwiyata', 'subcategory_name' => 'Laporan Adiwiyata', 'cabinet_number' => 6, 'priority' => 1],

            ['category_name' => 'RKT, RKAS, RKJM', 'subcategory_name' => null, 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'RKT, RKAS, RKJM', 'subcategory_name' => 'RKT', 'cabinet_number' => 6, 'priority' => 1],
            ['category_name' => 'RKT, RKAS, RKJM', 'subcategory_name' => 'RKAS', 'cabinet_number' => 7, 'priority' => 1],
            ['category_name' => 'RKT, RKAS, RKJM', 'subcategory_name' => 'RKJM', 'cabinet_number' => 6, 'priority' => 1],
        ];

        foreach ($items as $item) {
            $categoryId = $categoryIds[$item['category_name']] ?? null;
            $cabinetId = $cabinetIds[$item['cabinet_number']] ?? null;
            $subcategoryId = $item['subcategory_name']
                ? ($subcategoryIds[$item['category_name'].'|'.$item['subcategory_name']] ?? null)
                : null;

            if (! $categoryId || ! $cabinetId || ($item['subcategory_name'] && ! $subcategoryId)) {
                continue;
            }

            ArchiveStorageRule::query()->updateOrCreate(
                [
                    'category_id' => $categoryId,
                    'subcategory_id' => $subcategoryId,
                    'priority' => $item['priority'],
                ],
                [
                    'cabinet_id' => $cabinetId,
                ]
            );
        }
    }
}
