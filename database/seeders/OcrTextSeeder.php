<?php

namespace Database\Seeders;

use App\Models\Archive;
use Illuminate\Database\Seeder;

class OcrTextSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Archive::query()->with('files')->orderBy('id')->get() as $index => $archive) {
            if ($archive->status !== 'uploaded' || $index % 3 === 2) {
                $archive->ocrText()->delete();

                continue;
            }

            $archive->ocrText()->updateOrCreate(
                ['archive_id' => $archive->id],
                [
                    'extracted_text' => sprintf(
                        'Hasil OCR simulasi untuk arsip "%s" tahun %s dengan tipe file %s.',
                        $archive->title,
                        $archive->year ?? 'tanpa tahun',
                        $archive->files->file_type ?? 'unknown'
                    ),
                ]
            );
        }
    }
}
