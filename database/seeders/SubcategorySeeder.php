<?php

namespace Database\Seeders;

use App\Models\ArchiveCategory;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class SubcategorySeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'Data Siswa' => ['Data Pribadi', 'Nilai', 'Absensi', 'Mutasi'],
            'Data Guru dan Staf' => ['Guru ASN', 'Guru Honorer', 'Tenaga Kependidikan'],
            'Akademik/Kurikulum' => ['Jadwal Pelajaran', 'Modul Ajar / RPP', 'Kurikulum', 'Kalender Pendidikan', 'Tahfidz'],
            'Administrasi Sekolah dan Bendahara' => ['Surat Masuk', 'Surat Keluar', 'Notulen Rapat', 'SK', 'Laporan Kegiatan Sekolah'],
            'Inventaris Sekolah' => ['Aset Tetap', 'Laboratorium', 'Perpustakaan'],
            'Kesiswaan dan BK' => ['OSIS', 'Prestasi Siswa', 'Bimbingan Konseling', 'Ekstrakurikuler'],
        ];

        foreach ($map as $categoryName => $subcategories) {
            $category = ArchiveCategory::query()->where('name', $categoryName)->first();

            if (! $category) {
                continue;
            }

            foreach ($subcategories as $name) {
                Subcategory::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                    ],
                    []
                );
            }
        }
    }
}
