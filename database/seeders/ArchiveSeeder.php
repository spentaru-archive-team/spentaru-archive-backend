<?php

namespace Database\Seeders;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\Event;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ArchiveSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ArchiveCategorySeeder::class,
            SubcategorySeeder::class,
            CabinetSeeder::class,
            RackSeeder::class,
            EventSeeder::class,
        ]);

        $categories = ArchiveCategory::query()->orderBy('id')->get()->keyBy('name');
        $subcategories = Subcategory::query()->orderBy('id')->get()->groupBy('category_id');
        $users = User::query()->orderBy('id')->get();
        $events = Event::query()->orderBy('id')->get();

        $documents = [
            'Surat Keputusan',
            'Laporan Kegiatan',
            'Berita Acara',
            'Notulen Rapat',
            'Daftar Hadir',
            'Proposal',
            'Rencana Anggaran',
            'Rekapitulasi',
            'Surat Tugas',
            'Dokumentasi',
            'Evaluasi Program',
            'Sertifikat Kegiatan',
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
            'Festival Literasi',
            'Monitoring BOS',
        ];

        $categoryNames = $categories->keys()->values();
        $retentionCycle = ['active', 'active', 'retained', 'ready_for_destruction', 'destroyed', 'active'];

        for ($i = 1; $i <= 36; $i++) {
            $categoryName = $categoryNames[($i - 1) % $categoryNames->count()];
            $category = $categories[$categoryName];
            $subcategoryPool = $subcategories->get($category->id, collect());
            $subcategory = $this->resolveSubcategory($subcategoryPool, $i);
            $retentionStatus = $retentionCycle[($i - 1) % count($retentionCycle)];
            $year = 2021 + ($i % 6);
            $dueDate = Carbon::create($year + 2, (($i - 1) % 12) + 1, min(20, 5 + $i));
            $decider = $retentionStatus === 'active'
                ? null
                : $users->firstWhere('role', 'admin') ?? $users->first();

            Archive::query()->updateOrCreate(
                ['title' => sprintf('%s %s %02d', $documents[($i - 1) % count($documents)], $subjects[($i - 1) % count($subjects)], $i)],
                [
                    'event_id' => $events->isEmpty() || $i % 7 === 0
                        ? null
                        : $events[($i - 1) % $events->count()]->id,
                    'year' => $year,
                    'notes' => $i % 5 === 0
                        ? null
                        : 'Dokumen arsip untuk kebutuhan audit, pelaporan, atau dokumentasi kegiatan sekolah.',
                    'category_id' => $category->id,
                    'subcategory_id' => $subcategory?->id,
                    'uploader' => $users[($i - 1) % $users->count()]->id,
                    'retention_due_date' => $i % 8 === 0 ? null : $dueDate->toDateString(),
                    'retention_status' => $retentionStatus,
                    'retention_decided_at' => $retentionStatus === 'active' ? null : $dueDate->copy()->addMonths(2),
                    'retention_decided_by' => $decider?->id,
                    'retention_note' => $this->resolveRetentionNote($retentionStatus, $i),
                ]
            );
        }

        Event::query()->get()->each(function (Event $event): void {
            $event->update([
                'softfile_status' => $event->archives()
                    ->whereHas('files')
                    ->exists()
                    ? 'uploaded'
                    : 'pending_upload',
            ]);
        });
    }

    private function resolveSubcategory(Collection $subcategoryPool, int $index): ?Subcategory
    {
        if ($subcategoryPool->isEmpty() || $index % 6 === 0) {
            return null;
        }

        return $subcategoryPool[($index - 1) % $subcategoryPool->count()];
    }

    private function resolveRetentionNote(string $retentionStatus, int $index): ?string
    {
        return match ($retentionStatus) {
            'retained' => 'Arsip dipertahankan karena masih sering dipakai untuk referensi historis.',
            'ready_for_destruction' => 'Arsip masuk daftar telaah pemusnahan batch '.(2026 + ($index % 2)).'.',
            'destroyed' => 'Arsip sudah dimusnahkan sesuai berita acara retensi.',
            default => null,
        };
    }
}
