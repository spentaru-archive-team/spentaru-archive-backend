<?php

namespace Database\Factories;

use App\Models\Archive;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Archive>
 */
class ArchiveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentTypes = [
            'Proposal Kegiatan',
            'Surat Tugas',
            'Laporan Pertanggungjawaban',
            'Notulen Rapat',
            'Daftar Hadir',
            'Undangan Resmi',
            'Dokumentasi Kegiatan',
            'Surat Izin',
            'Berita Acara',
            'Rekap Administrasi',
        ];

        $subjects = [
            'MPLS',
            'Pesantren Ramadhan',
            'Class Meeting',
            'Peringatan Hari Kartini',
            'Workshop Guru',
            'ANBK',
            'Try Out Sekolah',
            'P5 Kewirausahaan',
            'Perpisahan Kelas IX',
            'Lomba Kebersihan Kelas',
            'Rapat Komite',
            'Penerimaan Raport',
        ];

        $title = fake()->randomElement($documentTypes).' '.fake()->randomElement($subjects);

        return [
            'title' => $title,
            'year' => fake()->numberBetween(2020, 2026),
            'notes' => fake()->boolean(80)
                ? fake()->sentence(fake()->numberBetween(8, 14))
                : null,
            'status' => fake()->randomElement(['pending_upload', 'uploaded']),
        ];
    }
}
