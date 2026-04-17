<?php

namespace Database\Seeders;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveStorageRule;
use App\Models\Cabinet;
use App\Models\Event;
use App\Models\Rack;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ArchiveSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'role' => 'admin',
            ]
        );

        $categories = $this->seedCategories();
        $subcategories = $this->seedSubcategories($categories);
        $events = $this->seedEvents($admin);
        $racks = $this->seedStorage();
        $this->seedStorageRules($categories, $racks);

        $documents = [
            'Surat Keputusan',
            'Laporan Kegiatan',
            'Berita Acara',
            'Notulen Rapat',
            'Daftar Hadir',
            'Dokumentasi',
            'Proposal',
            'Rencana Anggaran',
            'Rekapitulasi',
            'Surat Tugas',
        ];

        $subjects = [
            'MPLS',
            'ANBK',
            'P5 Kewirausahaan',
            'Class Meeting',
            'Workshop Guru',
            'Rapat Komite',
            'Pesantren Ramadhan',
            'Hari Kartini',
            'Perpisahan Kelas IX',
            'Inventaris Lab',
        ];

        for ($i = 1; $i <= 50; $i++) {
            $category = $categories[($i - 1) % $categories->count()];
            $subcategoryPool = $subcategories->get($category->id, collect());
            $subcategory = $subcategoryPool->isNotEmpty()
                ? $subcategoryPool[($i - 1) % $subcategoryPool->count()]
                : null;
            $event = $events[($i - 1) % $events->count()];
            $status = $i <= 35 ? 'uploaded' : 'pending_upload';
            $title = sprintf(
                '%s %s %02d',
                $documents[($i - 1) % count($documents)],
                $subjects[($i - 1) % count($subjects)],
                $i
            );

            $archive = Archive::query()->updateOrCreate(
                ['title' => $title],
                [
                    'event_id' => $i % 5 === 0 ? null : $event->id,
                    'year' => 2022 + ($i % 5),
                    'notes' => $i % 4 === 0 ? null : 'Arsip nomor '.$i.' untuk kebutuhan dokumentasi sekolah.',
                    'category_id' => $category->id,
                    'subcategory_id' => $i % 6 === 0 || $subcategory === null ? null : $subcategory->id,
                    'status' => $status,
                ]
            );

            if ($status !== 'uploaded') {
                $archive->files()->delete();
                $archive->physicalLocation()->delete();
                $archive->ocrText()->delete();

                continue;
            }

            $rack = $racks[($i - 1) % $racks->count()];
            $slotNumber = intdiv($i - 1, $racks->count()) + 1;
            $extension = ['pdf', 'docx', 'xlsx'][($i - 1) % 3];
            $basename = Str::slug($title, '_');

            $archive->files()->updateOrCreate(
                ['archive_id' => $archive->id],
                [
                    'file_name' => $basename.'.'.$extension,
                    'file_size' => 120000 + ($i * 157),
                    'file_type' => $extension,
                    'file_url' => '/storage/uploads/'.$basename.'.'.$extension,
                ]
            );

            $archive->physicalLocation()->updateOrCreate(
                ['archive_id' => $archive->id],
                [
                    'cabinet_id' => $rack->cabinet_id,
                    'rack_id' => $rack->id,
                    'slot_number' => $slotNumber,
                    'label_code' => 'L'.$rack->cabinet_id.'-R'.$rack->id.'-S'.$slotNumber,
                    'notes' => 'Lokasi fisik arsip '.$title,
                ]
            );

            $archive->ocrText()->updateOrCreate(
                ['archive_id' => $archive->id],
                [
                    'extracted_text' => 'Hasil OCR simulasi untuk '.$title.' tahun '.(2022 + ($i % 5)).'.',
                ]
            );
        }
    }

    private function seedCategories(): Collection
    {
        $items = [
            ['name' => 'Data Siswa', 'description' => 'Arsip data pribadi siswa.'],
            ['name' => 'Data Guru dan Staf', 'description' => 'Arsip data guru dan tenaga kependidikan.'],
            ['name' => 'Akademik/Kurikulum', 'description' => 'Arsip dokumen terkait kegiatan pembelajaran dan kurikulum.'],
            ['name' => 'Administrasi Sekolah dan Bendahara', 'description' => 'Arsip dokumen administrasi umum dan keuangan sekolah.'],
            ['name' => 'Inventaris Sekolah', 'description' => 'Arsip dokumen terkait sarana dan prasarana sekolah.'],
            ['name' => 'Kesiswaan dan BK', 'description' => 'Arsip dokumen terkait kegiatan kesiswaan dan bimbingan konseling.'],
            ['name' => 'Arsip Alumni', 'description' => 'Arsip data dan dokumen terkait alumni sekolah.'],
            ['name' => 'MBG', 'description' => 'Arsip dokumen terkait program Makan Bergizi Gratis.'],
            ['name' => 'Program Literasi', 'description' => 'Arsip dokumen terkait program literasi sekolah.'],
            ['name' => 'Pengembang Sekolah', 'description' => 'Arsip dokumen terkait program pengembang sekolah.'],
            ['name' => 'Backup / Arsip Dokumen', 'description' => 'Arsip dokumen yang sudah tidak aktif namun perlu disimpan sebagai backup.'],
            ['name' => 'Program Adiwiyata', 'description' => 'Arsip dokumen terkait program Adiwiyata dan kegiatan lingkungan hidup.'],
            ['name' => 'RKT, RKAS, RKJM', 'description' => 'Arsip dokumen perencanaan sekolah seperti Rencana Kerja Tahunan, Rencana Kegiatan dan Anggaran Sekolah, dan Rencana Kerja Jangka Menengah.'],
        ];

        foreach ($items as $item) {
            ArchiveCategory::query()->updateOrCreate(
                ['name' => $item['name']],
                ['description' => $item['description']]
            );
        }

        return ArchiveCategory::query()->orderBy('id')->get();
    }

    private function seedSubcategories(Collection $categories): Collection
    {
        $map = [
            'Data Siswa' => ['Data Pribadi', 'Nilai', 'Absensi'],
            'Data Guru dan Staf' => ['Guru ASN', 'Guru Honorer', 'Tenaga Pendidikan'],
            'Akademik/Kurikulum' => ['Jadwal Pelajaran', 'Modul Ajar / RPP', 'Kurikulum', 'Nilai Siswa', 'Kalender Pendidikan', 'Tahfidz'],
            'Administrasi Sekolah dan Bendahara' => ['Surat Masuk', 'Surat Keluar', 'Notulen Rapat', 'SK', 'MOU / Kerja Sama', 'Laporan Kegiatan Sekolah', 'SOP', 'Jadwal Piker', 'Program Kerja Tugas Tambahan Guru'],
            'Inventaris Sekolah' => ['Aset Tetap', 'Laboratorium', 'Perpustakaan'],
            'Kesiswaan dan BK' => ['Ekstrakurikuler', 'Pelanggaran dan Pembinaan', 'Prestasi Siswa', 'Data Psikotes', 'Bimbingan Konseling', 'OSIS'],
        ];

        foreach ($map as $categoryName => $names) {
            $category = $categories->firstWhere('name', $categoryName);

            foreach ($names as $name) {
                Subcategory::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                    ],
                    []
                );
            }
        }

        return Subcategory::query()
            ->orderBy('id')
            ->get()
            ->groupBy('category_id');
    }

    private function seedEvents(User $admin): Collection
    {
        $items = [
            ['title' => 'MPLS 2026', 'description' => 'Kegiatan orientasi peserta didik baru.', 'date' => '2026-07-15', 'status' => 'ongoing'],
            ['title' => 'Workshop Guru', 'description' => 'Peningkatan kompetensi guru.', 'date' => '2026-06-10', 'status' => 'done'],
            ['title' => 'Class Meeting', 'description' => 'Kegiatan akhir semester untuk siswa.', 'date' => '2026-06-25', 'status' => 'ongoing'],
            ['title' => 'Pesantren Ramadhan', 'description' => 'Program pembinaan karakter siswa.', 'date' => '2026-03-20', 'status' => 'done'],
            ['title' => 'Rapat Komite', 'description' => 'Koordinasi program sekolah dengan komite.', 'date' => '2026-05-12', 'status' => 'ongoing'],
            ['title' => 'P5 Kewirausahaan', 'description' => 'Proyek profil pelajar pancasila.', 'date' => '2026-08-01', 'status' => 'ongoing'],
        ];

        foreach ($items as $item) {
            Event::query()->updateOrCreate(
                ['title' => $item['title']],
                $item + ['user_id' => $admin->id]
            );
        }

        return Event::query()->orderBy('id')->get();
    }

    private function seedStorage(): Collection
    {
        $cabinetNames = [
            'Standar Isi',
            'Standar Proses',
            'Standar Kompetensi Lulusan',
            'Standar Pendidik & Tenaga Kependidikan',
            'Standar Sarana Prasarana',
            'Standar Pengelolaan',
            'Standar Pembiayaan',
            'Standar Penilaian',
            'Campuran',
            'Lemari Soal Ujian/Sumatif',
            'Lemari Laporan Dana BOS (1)',
            'Lemari Kosong',
            'Lemari Laporan Dana BOS (2)',
            'Lemari Laporan Dana BOS (3)',
        ];

        foreach ($cabinetNames as $cabinetName) {
            Cabinet::query()->updateOrCreate(['name' => $cabinetName], []);
        }

        $cabinets = Cabinet::query()->orderBy('id')->get();
        $hasUsedCapacityColumn = Schema::hasColumn('racks', 'used_capacity');

        foreach ($cabinets as $cabinet) {
            for ($rackNumber = 1; $rackNumber <= 4; $rackNumber++) {
                $rackAttributes = ['capacity' => 20];

                if ($hasUsedCapacityColumn) {
                    $rackAttributes['used_capacity'] = random_int(0, 20);
                }

                Rack::query()->updateOrCreate(
                    [
                        'cabinet_id' => $cabinet->id,
                        'rack_number' => $rackNumber,
                    ],
                    $rackAttributes
                );
            }
        }

        return Rack::query()->orderBy('id')->get();
    }

    private function seedStorageRules(Collection $categories, Collection $racks): void
    {
        $rules = [
            ['name' => 'Data Siswa', 'cabinet_id' => 1, 'priority' => 10],
            ['name' => 'Data Guru dan Staf', 'cabinet_id' => 1, 'priority' => 9],
            ['name' => 'Akademik/Kurikulum', 'cabinet_id' => 2, 'priority' => 8],
            ['name' => 'Administrasi Sekolah dan Bendahara', 'cabinet_id' => 2, 'priority' => 7],
            ['name' => 'Inventaris Sekolah', 'cabinet_id' => 3, 'priority' => 6],
        ];

        foreach ($rules as $rule) {
            $category = $categories->firstWhere('name', $rule['name']);

            if ($category) {
                ArchiveStorageRule::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'subcategory_id' => null,
                    ],
                    [
                        'cabinet_id' => $rule['cabinet_id'],
                        'priority' => $rule['priority'],
                    ]
                );
            }
        }
    }
}
