<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('archive_files', 'vector_id')) {
            DB::table('archive_files')
                ->select(['archive_id', 'vector_id'])
                ->whereNotNull('vector_id')
                ->orderBy('archive_id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('ocr_texts')
                            ->where('archive_id', $row->archive_id)
                            ->update([
                                'vector_id' => $row->vector_id,
                            ]);
                    }
                });

            Schema::table('archive_files', function (Blueprint $table) {
                $table->dropColumn('vector_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('archive_files', 'vector_id')) {
            Schema::table('archive_files', function (Blueprint $table) {
                $table->string('vector_id', 36)->nullable()->after('file_type');
            });

            DB::table('ocr_texts')
                ->select(['archive_id', 'vector_id'])
                ->whereNotNull('vector_id')
                ->orderBy('archive_id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('archive_files')
                            ->where('archive_id', $row->archive_id)
                            ->update([
                                'vector_id' => $row->vector_id,
                            ]);
                    }
                });
        }
    }
};
