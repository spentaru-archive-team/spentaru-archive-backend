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
            'Arsip Alumni' => ['Tracer Study', 'Administrasi Alumni'],
            'MBG' => ['Program MBG', 'Laporan MBG'],
            'Program Literasi' => ['Pojok Baca', 'Jurnal Literasi', 'Monitoring Literasi'],
            'Pengembang Sekolah' => ['Peningkatan Mutu', 'Pengembangan Sekolah'],
            'Backup / Arsip Dokumen' => ['Backup Harian', 'Backup Mingguan', 'Backup Bulanan'],
            'Program Adiwiyata' => ['Program Adiwiyata', 'Laporan Adiwiyata'],
            'RKT, RKAS, RKJM' => ['RKT', 'RKAS', 'RKJM'],
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
