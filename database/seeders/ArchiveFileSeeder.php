<?php

namespace Database\Seeders;

use App\Models\Archive;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArchiveFileSeeder extends Seeder
{
    public function run(): void
    {
        $extensions = ['pdf', 'docx', 'xlsx', 'jpg', 'png'];

        foreach (Archive::query()->orderBy('id')->get() as $index => $archive) {
            if ($archive->status !== 'uploaded') {
                $archive->files()->delete();

                continue;
            }

            $extension = $extensions[$index % count($extensions)];
            $baseName = Str::slug($archive->title, '_');

            $archive->files()->updateOrCreate(
                ['archive_id' => $archive->id],
                [
                    'file_name' => $baseName.'.'.$extension,
                    'file_size' => 125000 + (($index + 1) * 1731),
                    'file_type' => $extension,
                    'file_url' => '/storage/uploads/'.$baseName.'.'.$extension,
                ]
            );
        }
    }
}
