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
            $subcategoryPool = $subcategories->get($category->id);
            $subcategory = $subcategoryPool[($i - 1) % $subcategoryPool->count()];
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
                    'subcategory_id' => $i % 6 === 0 ? null : $subcategory->id,
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
            ['name' => 'Akademik', 'description' => 'Arsip kegiatan akademik dan evaluasi pembelajaran.'],
            ['name' => 'Kesiswaan', 'description' => 'Arsip aktivitas siswa dan organisasi sekolah.'],
            ['name' => 'Kepegawaian', 'description' => 'Arsip administrasi guru dan tenaga kependidikan.'],
            ['name' => 'Sarana Prasarana', 'description' => 'Arsip inventaris dan fasilitas sekolah.'],
            ['name' => 'Humas', 'description' => 'Arsip komunikasi eksternal dan kerja sama sekolah.'],
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
            'Akademik' => ['Ujian', 'Modul Ajar', 'Jadwal Pembelajaran'],
            'Kesiswaan' => ['OSIS', 'Ekstrakurikuler', 'Bimbingan Siswa'],
            'Kepegawaian' => ['Rapat Guru', 'Surat Tugas', 'Penilaian Kinerja'],
            'Sarana Prasarana' => ['Inventaris', 'Pemeliharaan', 'Peminjaman Ruang'],
            'Humas' => ['Undangan', 'Publikasi', 'Kerja Sama'],
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
        $cabinetNames = ['Lemari A', 'Lemari B', 'Lemari C'];

        foreach ($cabinetNames as $cabinetName) {
            Cabinet::query()->updateOrCreate(['name' => $cabinetName], []);
        }

        $cabinets = Cabinet::query()->orderBy('id')->get();

        foreach ($cabinets as $cabinet) {
            for ($rackNumber = 1; $rackNumber <= 4; $rackNumber++) {
                Rack::query()->updateOrCreate(
                    [
                        'cabinet_id' => $cabinet->id,
                        'rack_number' => $rackNumber,
                    ],
                    [
                        'capacity' => 20,
                    ]
                );
            }
        }

        return Rack::query()->orderBy('id')->get();
    }

    private function seedStorageRules(Collection $categories, Collection $racks): void
    {
        $rules = [
            ['name' => 'Akademik', 'cabinet_id' => 1, 'priority' => 10],
            ['name' => 'Kesiswaan', 'cabinet_id' => 1, 'priority' => 9],
            ['name' => 'Kepegawaian', 'cabinet_id' => 2, 'priority' => 8],
            ['name' => 'Sarana Prasarana', 'cabinet_id' => 2, 'priority' => 7],
            ['name' => 'Humas', 'cabinet_id' => 3, 'priority' => 6],
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
