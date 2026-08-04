<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'username' => env('USERNAME', 'admin'),
                'name' => 'Admin Utama',
                'subject' => 'Administrasi',
                'position' => 'Administrator',
                'password' => env('PASSWORD', 'password'),
                'role' => 'admin',
                'last_login_at' => now(),
            ],
            [
                'username' => 'admin',
                'name' => 'Admin Utama',
                'subject' => 'Administrasi',
                'position' => 'Administrator',
                'password' => 'password',
                'role' => 'admin',
                'last_login_at' => now(),
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
