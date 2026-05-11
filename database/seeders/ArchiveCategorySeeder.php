<?php

namespace Database\Seeders;

use App\Models\ArchiveCategory;
use Illuminate\Database\Seeder;

class ArchiveCategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'Data Siswa',
                'description' => 'Arsip data pribadi, mutasi, dan dokumen akademik siswa.',
                'has_subcategory' => true,
            ],
            [
                'name' => 'Data Guru dan Staf',
                'description' => 'Arsip pegawai, SK tugas, dan administrasi tenaga pendidik.',
                'has_subcategory' => true,
            ],
            [
                'name' => 'Akademik/Kurikulum',
                'description' => 'Dokumen pembelajaran, kurikulum, perangkat ajar, dan evaluasi.',
                'has_subcategory' => true,
            ],
            [
                'name' => 'Administrasi Sekolah dan Bendahara',
                'description' => 'Surat menyurat, notulen, program kerja, dan laporan keuangan.',
                'has_subcategory' => true,
            ],
            [
                'name' => 'Inventaris Sekolah',
                'description' => 'Dokumen aset, sarpras, dan pengelolaan inventaris.',
                'has_subcategory' => true,
            ],
            [
                'name' => 'Kesiswaan dan BK',
                'description' => 'Arsip OSIS, pembinaan, prestasi, dan layanan BK.',
                'has_subcategory' => true,
            ],
            [
                'name' => 'Arsip Alumni',
                'description' => 'Dokumen tracer study dan administrasi alumni.',
                'has_subcategory' => false,
            ],
            [
                'name' => 'MBG',
                'description' => 'Arsip program Makan Bergizi Gratis.',
                'has_subcategory' => false,
            ],
            [
                'name' => 'Program Literasi',
                'description' => 'Arsip pojok baca, jurnal literasi, dan monitoring.',
                'has_subcategory' => false,
            ],
            [
                'name' => 'Pengembang Sekolah',
                'description' => 'Dokumen peningkatan mutu dan pengembangan sekolah.',
                'has_subcategory' => false,
            ],
            [
                'name' => 'Backup / Arsip Dokumen',
                'description' => 'Dokumen pasif yang tetap perlu disimpan sebagai cadangan.',
                'has_subcategory' => false,
            ],
            [
                'name' => 'Program Adiwiyata',
                'description' => 'Arsip kegiatan lingkungan hidup dan Adiwiyata.',
                'has_subcategory' => false,
            ],
            [
                'name' => 'RKT, RKAS, RKJM',
                'description' => 'Dokumen perencanaan tahunan dan jangka menengah sekolah.',
                'has_subcategory' => false,
            ],
        ];

        foreach ($items as $item) {
            ArchiveCategory::query()->updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}
