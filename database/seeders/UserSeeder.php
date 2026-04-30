<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'username' => 'admin',
                'name' => 'Admin Utama',
                'subject' => 'Administrasi',
                'position' => 'Administrator',
                'password' => 'password',
                'role' => 'admin',
                'last_login_at' => now()->subHours(2),
            ],
            [
                'username' => 'waka_kurikulum',
                'name' => 'Nadia Kurikulum',
                'subject' => 'Kurikulum',
                'position' => 'Waka Kurikulum',
                'password' => 'password',
                'role' => 'guru',
                'last_login_at' => now()->subDay(),
            ],
            [
                'username' => 'guru_bindo',
                'name' => 'Rizki Pratama',
                'subject' => 'Bahasa Indonesia',
                'position' => 'Guru Mapel',
                'password' => 'password',
                'role' => 'guru',
                'last_login_at' => now()->subDays(3),
            ],
            [
                'username' => 'guru_mtk',
                'name' => 'Salsa Permata',
                'subject' => 'Matematika',
                'position' => 'Guru Mapel',
                'password' => 'password',
                'role' => 'guru',
                'last_login_at' => null,
            ],
            [
                'username' => 'guru_ips',
                'name' => 'Dian Saputra',
                'subject' => 'IPS',
                'position' => 'Wali Kelas',
                'password' => 'password',
                'role' => 'guru',
                'last_login_at' => now()->subWeek(),
            ],
            [
                'username' => 'guru_tik',
                'name' => 'Fahmi Akbar',
                'subject' => 'Informatika',
                'position' => 'Operator Arsip',
                'password' => 'password',
                'role' => 'guru',
                'last_login_at' => now()->subHours(8),
            ],
            [
                'username' => 'guru_bk',
                'name' => 'Lina Maharani',
                'subject' => 'Bimbingan Konseling',
                'position' => 'Guru BK',
                'password' => 'password',
                'role' => 'guru',
                'last_login_at' => null,
            ],
        ];

        foreach ($items as $item) {
            User::query()->updateOrCreate(
                ['username' => $item['username']],
                $item
            );
        }
    }
}
