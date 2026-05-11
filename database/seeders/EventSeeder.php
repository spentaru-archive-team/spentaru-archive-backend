<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $owners = User::query()->pluck('id', 'username');

        $items = [
            [
                'title' => 'MPLS 2026',
                'username' => 'waka_kurikulum',
                'description' => 'Kegiatan orientasi peserta didik baru tahun ajaran 2026/2027.',
                'date' => '2026-07-15',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Workshop Guru Semester Ganjil',
                'username' => 'admin',
                'description' => 'Peningkatan kompetensi guru dalam penyusunan modul ajar.',
                'date' => '2026-06-10',
                'status' => 'done',
            ],
            [
                'title' => 'Class Meeting 2026',
                'username' => 'guru_bk',
                'description' => 'Kegiatan akhir semester siswa lintas kelas.',
                'date' => '2026-06-25',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Pesantren Ramadhan',
                'username' => 'guru_bindo',
                'description' => null,
                'date' => '2026-03-20',
                'status' => 'done',
            ],
            [
                'title' => 'Rapat Komite Sekolah',
                'username' => 'admin',
                'description' => 'Koordinasi program sekolah bersama komite.',
                'date' => '2026-05-12',
                'status' => 'ongoing',
            ],
            [
                'title' => 'P5 Kewirausahaan',
                'username' => 'guru_ips',
                'description' => 'Proyek profil pelajar pancasila tema kewirausahaan.',
                'date' => '2026-08-01',
                'status' => 'ongoing',
            ],
            [
                'title' => 'Audit Inventaris Lab',
                'username' => 'guru_tik',
                'description' => 'Pemeriksaan inventaris laboratorium komputer.',
                'date' => '2026-04-08',
                'status' => 'done',
            ],
            [
                'title' => 'Festival Literasi Sekolah',
                'username' => 'guru_bindo',
                'description' => 'Program literasi tahunan dengan showcase karya siswa.',
                'date' => '2026-09-02',
                'status' => 'ongoing',
            ],
        ];

        foreach ($items as $item) {
            Event::query()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'user_id' => $owners[$item['username']] ?? $owners['admin'],
                    'description' => $item['description'],
                    'date' => $item['date'],
                    'status' => $item['status'],
                    'softfile_status' => 'pending_upload',
                ]
            );
        }
    }
}
