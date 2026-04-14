<?php

namespace Database\Seeders;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveFile;
use App\Models\ArchivePhysicalLocation;
use App\Models\ArchiveStorageRule;
use App\Models\Cabinet;
use App\Models\Event;
use App\Models\OcrText;
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
        $users = $this->ensureUsers();
        $categories = $this->seedCategories()->keyBy('name');
        $subcategories = $this->seedSubcategories($categories)->keyBy(fn (Subcategory $subcategory) => $this->subcategoryKey(
            $subcategory->category->name,
            $subcategory->name
        ));
        $events = $this->seedEvents($users)->keyBy('title');
        $cabinets = $this->seedCabinets()->keyBy('name');
        $racks = $this->seedRacks($cabinets)->keyBy(fn (Rack $rack) => $this->rackKey($rack->cabinet_id, $rack->rack_number));

        $this->seedStorageRules($categories, $subcategories, $cabinets);
        $this->seedArchives($categories, $subcategories, $events, $cabinets, $racks);
    }

    protected function ensureUsers(): Collection
    {
        if (User::query()->doesntExist()) {
            User::factory()->count(3)->admin()->create();
            User::factory()->count(12)->guru()->create();
        }

        return User::query()->get();
    }

    protected function seedCategories(): Collection
    {
        $categories = [
            [
                'name' => 'Akademik',
                'description' => 'Arsip kegiatan pembelajaran, ujian, dan evaluasi akademik.',
            ],
            [
                'name' => 'Kesiswaan',
                'description' => 'Arsip kegiatan siswa, pembinaan, dan agenda OSIS.',
            ],
            [
                'name' => 'Kepegawaian',
                'description' => 'Arsip tugas guru, rapat, dan administrasi tenaga pendidik.',
            ],
            [
                'name' => 'Sarana Prasarana',
                'description' => 'Arsip inventaris, pemeliharaan, dan penggunaan fasilitas sekolah.',
            ],
            [
                'name' => 'Humas',
                'description' => 'Arsip komunikasi eksternal, undangan, dan kerja sama sekolah.',
            ],
            [
                'name' => 'Keuangan',
                'description' => 'Arsip pendanaan kegiatan, laporan biaya, dan pertanggungjawaban.',
            ],
        ];

        foreach ($categories as $category) {
            ArchiveCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }

        return ArchiveCategory::query()->get();
    }

    protected function seedSubcategories(Collection $categories): Collection
    {
        $subcategories = [
            'Akademik' => ['Ujian', 'Modul Ajar', 'Jadwal Pembelajaran'],
            'Kesiswaan' => ['OSIS', 'Ekstrakurikuler', 'Disiplin Siswa'],
            'Kepegawaian' => ['Rapat Guru', 'Surat Tugas', 'Penilaian Kinerja'],
            'Sarana Prasarana' => ['Inventaris', 'Pemeliharaan', 'Peminjaman Ruang'],
            'Humas' => ['Undangan', 'Kerja Sama', 'Publikasi'],
            'Keuangan' => ['RAB', 'Bukti Transaksi', 'LPJ'],
        ];

        foreach ($subcategories as $categoryName => $names) {
            $category = $categories->get($categoryName);

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

        return Subcategory::query()->with('category')->get();
    }

    protected function seedEvents(Collection $users): Collection
    {
        $events = [
            [
                'title' => 'Masa Pengenalan Lingkungan Sekolah',
                'description' => 'Kegiatan orientasi siswa baru tahun ajaran berjalan.',
                'date' => '2026-07-15',
                'status' => 'done',
            ],
            [
                'title' => 'Workshop Penyusunan Modul Ajar',
                'description' => 'Pelatihan internal guru untuk penyusunan modul ajar semester ganjil.',
                'date' => '2026-06-20',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Class Meeting Semester Genap',
                'description' => 'Agenda lomba antar kelas setelah penilaian akhir semester.',
                'date' => '2026-06-28',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Peringatan Hari Kartini',
                'description' => 'Kegiatan peringatan Hari Kartini tingkat sekolah.',
                'date' => '2026-04-21',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Simulasi ANBK',
                'description' => 'Persiapan teknis dan administrasi Asesmen Nasional Berbasis Komputer.',
                'date' => '2026-08-10',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Pesantren Ramadhan',
                'description' => 'Program pembinaan karakter dan ibadah siswa selama Ramadhan.',
                'date' => '2026-03-18',
                'status' => 'done',
            ],
            [
                'title' => 'Rapat Komite Sekolah',
                'description' => 'Koordinasi program kerja dan dukungan komite sekolah.',
                'date' => '2026-05-11',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Try Out Ujian Sekolah',
                'description' => 'Kegiatan uji coba soal untuk siswa kelas akhir.',
                'date' => '2026-02-12',
                'status' => 'done',
            ],
            [
                'title' => 'P5 Tema Kewirausahaan',
                'description' => 'Proyek penguatan profil pelajar Pancasila bidang kewirausahaan.',
                'date' => '2026-09-03',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Perpisahan Siswa Kelas IX',
                'description' => 'Agenda pelepasan siswa kelas akhir beserta dokumentasi kegiatan.',
                'date' => '2026-05-28',
                'status' => 'done',
            ],
        ];

        $userIds = $users->pluck('id')->values();

        foreach ($events as $index => $event) {
            Event::query()->updateOrCreate(
                [
                    'title' => $event['title'],
                    'date' => $event['date'],
                ],
                [
                    'user_id' => $userIds[$index % $userIds->count()],
                    'description' => $event['description'],
                    'status' => $event['status'],
                ]
            );
        }

        return Event::query()->get();
    }

    protected function seedCabinets(): Collection
    {
        $cabinetNames = [
            'Lemari A',
            'Lemari B',
            'Lemari C',
        ];

        foreach ($cabinetNames as $name) {
            Cabinet::query()->updateOrCreate(['name' => $name], []);
        }

        return Cabinet::query()->get();
    }

    protected function seedRacks(Collection $cabinets): Collection
    {
        $rackMap = [
            'Lemari A' => [1, 2, 3],
            'Lemari B' => [1, 2, 3],
            'Lemari C' => [1, 2],
        ];

        foreach ($rackMap as $cabinetName => $rackNumbers) {
            $cabinet = $cabinets->get($cabinetName);

            foreach ($rackNumbers as $rackNumber) {
                Rack::query()->updateOrCreate(
                    [
                        'cabinet_id' => $cabinet->id,
                        'rack_number' => $rackNumber,
                    ],
                    [
                        'capacity' => 40,
                    ]
                );
            }
        }

        return Rack::query()->get();
    }

    protected function seedStorageRules(Collection $categories, Collection $subcategories, Collection $cabinets): void
    {
        $rules = [
            ['category' => 'Akademik', 'subcategory' => 'Ujian', 'cabinet' => 'Lemari A', 'priority' => 1],
            ['category' => 'Akademik', 'subcategory' => 'Modul Ajar', 'cabinet' => 'Lemari A', 'priority' => 2],
            ['category' => 'Kesiswaan', 'subcategory' => 'OSIS', 'cabinet' => 'Lemari B', 'priority' => 1],
            ['category' => 'Kepegawaian', 'subcategory' => 'Surat Tugas', 'cabinet' => 'Lemari B', 'priority' => 2],
            ['category' => 'Sarana Prasarana', 'subcategory' => 'Inventaris', 'cabinet' => 'Lemari C', 'priority' => 1],
            ['category' => 'Humas', 'subcategory' => 'Undangan', 'cabinet' => 'Lemari C', 'priority' => 2],
            ['category' => 'Keuangan', 'subcategory' => 'LPJ', 'cabinet' => 'Lemari A', 'priority' => 3],
        ];

        foreach ($rules as $rule) {
            $category = $categories->get($rule['category']);
            $subcategory = $subcategories->get($this->subcategoryKey($rule['category'], $rule['subcategory']));
            $cabinet = $cabinets->get($rule['cabinet']);

            ArchiveStorageRule::query()->updateOrCreate(
                [
                    'category_id' => $category?->id,
                    'subcategory_id' => $subcategory?->id,
                    'cabinet_id' => $cabinet->id,
                ],
                [
                    'priority' => $rule['priority'],
                ]
            );
        }
    }

    protected function seedArchives(
        Collection $categories,
        Collection $subcategories,
        Collection $events,
        Collection $cabinets,
        Collection $racks
    ): void {
        $archives = [
            [
                'title' => 'Berita Acara Simulasi ANBK Lab 1',
                'year' => 2026,
                'notes' => 'Dokumen verifikasi pelaksanaan simulasi dan daftar perangkat.',
                'status' => 'uploaded',
                'category' => 'Akademik',
                'subcategory' => 'Ujian',
                'event' => 'Simulasi ANBK',
                'cabinet' => 'Lemari A',
                'rack_number' => 1,
                'slot_number' => 1,
                'label_code' => 'AKD-UJN-001',
                'file_name' => 'berita-acara-simulasi-anbk.pdf',
                'file_type' => 'pdf',
                'file_size' => 284512,
                'ocr_text' => 'Berita acara simulasi ANBK laboratorium 1 SMP Terpadu tahun 2026.',
            ],
            [
                'title' => 'Modul Ajar Workshop Guru Semester Ganjil',
                'year' => 2026,
                'notes' => 'Versi final modul ajar hasil workshop internal guru.',
                'status' => 'uploaded',
                'category' => 'Akademik',
                'subcategory' => 'Modul Ajar',
                'event' => 'Workshop Penyusunan Modul Ajar',
                'cabinet' => 'Lemari A',
                'rack_number' => 1,
                'slot_number' => 2,
                'label_code' => 'AKD-MOD-002',
                'file_name' => 'modul-ajar-workshop-guru.docx',
                'file_type' => 'docx',
                'file_size' => 198764,
                'ocr_text' => 'Dokumen modul ajar semester ganjil untuk mata pelajaran inti.',
            ],
            [
                'title' => 'Surat Tugas Panitia MPLS 2026',
                'year' => 2026,
                'notes' => 'Penugasan guru dan panitia pelaksana MPLS.',
                'status' => 'uploaded',
                'category' => 'Kepegawaian',
                'subcategory' => 'Surat Tugas',
                'event' => 'Masa Pengenalan Lingkungan Sekolah',
                'cabinet' => 'Lemari B',
                'rack_number' => 1,
                'slot_number' => 1,
                'label_code' => 'KPG-STG-001',
                'file_name' => 'surat-tugas-panitia-mpls.pdf',
                'file_type' => 'pdf',
                'file_size' => 152330,
                'ocr_text' => 'Surat tugas panitia MPLS tahun ajaran 2026/2027.',
            ],
            [
                'title' => 'Notulen Rapat Komite Program Tahunan',
                'year' => 2026,
                'notes' => 'Ringkasan keputusan rapat komite sekolah.',
                'status' => 'uploaded',
                'category' => 'Kepegawaian',
                'subcategory' => 'Rapat Guru',
                'event' => 'Rapat Komite Sekolah',
                'cabinet' => 'Lemari B',
                'rack_number' => 1,
                'slot_number' => 2,
                'label_code' => 'KPG-RPT-002',
                'file_name' => 'notulen-rapat-komite.pdf',
                'file_type' => 'pdf',
                'file_size' => 173245,
                'ocr_text' => 'Notulen rapat komite sekolah terkait program tahunan dan evaluasi anggaran.',
            ],
            [
                'title' => 'Proposal P5 Tema Kewirausahaan',
                'year' => 2026,
                'notes' => 'Proposal pelaksanaan proyek P5 tema kewirausahaan.',
                'status' => 'uploaded',
                'category' => 'Kesiswaan',
                'subcategory' => 'Ekstrakurikuler',
                'event' => 'P5 Tema Kewirausahaan',
                'cabinet' => 'Lemari B',
                'rack_number' => 2,
                'slot_number' => 1,
                'label_code' => 'KSW-EKS-001',
                'file_name' => 'proposal-p5-kewirausahaan.pdf',
                'file_type' => 'pdf',
                'file_size' => 264119,
                'ocr_text' => 'Proposal kegiatan P5 kewirausahaan untuk siswa kelas VIII dan IX.',
            ],
            [
                'title' => 'Dokumentasi Perpisahan Siswa Kelas IX',
                'year' => 2026,
                'notes' => 'Tautan dokumentasi foto dan rundown acara.',
                'status' => 'uploaded',
                'category' => 'Kesiswaan',
                'subcategory' => 'OSIS',
                'event' => 'Perpisahan Siswa Kelas IX',
                'cabinet' => 'Lemari B',
                'rack_number' => 2,
                'slot_number' => 2,
                'label_code' => 'KSW-OSI-002',
                'file_name' => 'dokumentasi-perpisahan-kelas-ix.zip',
                'file_type' => 'zip',
                'file_size' => 1048576,
                'ocr_text' => 'Metadata dokumentasi kegiatan perpisahan siswa kelas IX tahun 2026.',
            ],
            [
                'title' => 'Inventaris Perangkat Simulasi ANBK',
                'year' => 2026,
                'notes' => 'Daftar inventaris perangkat komputer dan jaringan.',
                'status' => 'uploaded',
                'category' => 'Sarana Prasarana',
                'subcategory' => 'Inventaris',
                'event' => 'Simulasi ANBK',
                'cabinet' => 'Lemari C',
                'rack_number' => 1,
                'slot_number' => 1,
                'label_code' => 'SPR-INV-001',
                'file_name' => 'inventaris-perangkat-anbk.xlsx',
                'file_type' => 'xlsx',
                'file_size' => 86420,
                'ocr_text' => 'Inventaris perangkat simulasi ANBK meliputi PC, switch, dan access point.',
            ],
            [
                'title' => 'Undangan Resmi Peringatan Hari Kartini',
                'year' => 2026,
                'notes' => 'Undangan kegiatan untuk orang tua dan tamu sekolah.',
                'status' => 'uploaded',
                'category' => 'Humas',
                'subcategory' => 'Undangan',
                'event' => 'Peringatan Hari Kartini',
                'cabinet' => 'Lemari C',
                'rack_number' => 1,
                'slot_number' => 2,
                'label_code' => 'HMS-UND-001',
                'file_name' => 'undangan-hari-kartini.pdf',
                'file_type' => 'pdf',
                'file_size' => 120450,
                'ocr_text' => 'Undangan resmi kegiatan peringatan Hari Kartini tingkat sekolah.',
            ],
            [
                'title' => 'Laporan Pertanggungjawaban Pesantren Ramadhan',
                'year' => 2026,
                'notes' => 'Ringkasan penggunaan dana dan pelaksanaan kegiatan.',
                'status' => 'uploaded',
                'category' => 'Keuangan',
                'subcategory' => 'LPJ',
                'event' => 'Pesantren Ramadhan',
                'cabinet' => 'Lemari A',
                'rack_number' => 2,
                'slot_number' => 1,
                'label_code' => 'KEU-LPJ-001',
                'file_name' => 'lpj-pesantren-ramadhan.pdf',
                'file_type' => 'pdf',
                'file_size' => 245500,
                'ocr_text' => 'Laporan pertanggungjawaban kegiatan Pesantren Ramadhan tahun 2026.',
            ],
            [
                'title' => 'Jadwal Try Out Ujian Sekolah',
                'year' => 2026,
                'notes' => 'Jadwal distribusi ruang dan sesi try out.',
                'status' => 'pending_upload',
                'category' => 'Akademik',
                'subcategory' => 'Jadwal Pembelajaran',
                'event' => 'Try Out Ujian Sekolah',
            ],
            [
                'title' => 'Draft Bukti Transaksi Kegiatan Class Meeting',
                'year' => 2026,
                'notes' => 'Berkas masih menunggu upload final dari bendahara kegiatan.',
                'status' => 'pending_upload',
                'category' => 'Keuangan',
                'subcategory' => 'Bukti Transaksi',
                'event' => 'Class Meeting Semester Genap',
            ],
            [
                'title' => 'Rencana Peminjaman Ruang Seminar Guru',
                'year' => 2026,
                'notes' => 'Dokumen pengajuan masih tahap verifikasi sarana prasarana.',
                'status' => 'pending_upload',
                'category' => 'Sarana Prasarana',
                'subcategory' => 'Peminjaman Ruang',
                'event' => null,
            ],
        ];

        foreach ($archives as $archiveData) {
            $category = $categories->get($archiveData['category']);
            $subcategory = $subcategories->get($this->subcategoryKey($archiveData['category'], $archiveData['subcategory']));
            $event = $archiveData['event'] ? $events->get($archiveData['event']) : null;

            $archive = Archive::query()->updateOrCreate(
                [
                    'title' => $archiveData['title'],
                    'year' => $archiveData['year'],
                ],
                [
                    'event_id' => $event?->id,
                    'notes' => $archiveData['notes'],
                    'category_id' => $category->id,
                    'subcategory_id' => $subcategory?->id,
                    'status' => $archiveData['status'],
                ]
            );

            $this->syncArchiveRelatedData($archive, $archiveData, $cabinets, $racks);
        }
    }

    protected function syncArchiveRelatedData(
        Archive $archive,
        array $archiveData,
        Collection $cabinets,
        Collection $racks
    ): void {
        if ($archiveData['status'] !== 'uploaded') {
            ArchiveFile::query()->where('archive_id', $archive->id)->delete();
            ArchivePhysicalLocation::query()->where('archive_id', $archive->id)->delete();
            OcrText::query()->where('archive_id', $archive->id)->delete();

            return;
        }

        $fileName = $archiveData['file_name'];

        ArchiveFile::query()->updateOrCreate(
            [
                'archive_id' => $archive->id,
                'file_name' => $fileName,
            ],
            [
                'file_size' => $archiveData['file_size'],
                'file_type' => $archiveData['file_type'],
                'file_url' => '/storage/archives/'.Str::slug(pathinfo($fileName, PATHINFO_FILENAME)).'.'.pathinfo($fileName, PATHINFO_EXTENSION),
            ]
        );

        $cabinet = $cabinets->get($archiveData['cabinet']);
        $rack = $racks->get($this->rackKey($cabinet->id, $archiveData['rack_number']));

        ArchivePhysicalLocation::query()->updateOrCreate(
            ['archive_id' => $archive->id],
            [
                'cabinet_id' => $cabinet->id,
                'rack_id' => $rack->id,
                'slot_number' => $archiveData['slot_number'],
                'label_code' => $archiveData['label_code'],
                'notes' => 'Lokasi fisik untuk arsip '.$archive->title.'.',
            ]
        );

        OcrText::query()->updateOrCreate(
            ['archive_id' => $archive->id],
            ['extracted_text' => $archiveData['ocr_text']]
        );
    }

    protected function subcategoryKey(string $categoryName, string $subcategoryName): string
    {
        return $categoryName.'::'.$subcategoryName;
    }

    protected function rackKey(int $cabinetId, int $rackNumber): string
    {
        return $cabinetId.'::'.$rackNumber;
    }
}
