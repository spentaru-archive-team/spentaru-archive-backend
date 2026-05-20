<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_files', function (Blueprint $table) {
            $table->string('vector_id', 36)->after('file_type');
            $table->enum('extraction_status', ['pending', 'done', 'failed', 'no_text'])
                ->default('pending')
                ->after('vector_id');
        });
    }

    public function down(): void
    {
        Schema::table('archive_files', function (Blueprint $table) {
            $table->dropColumn(['extraction_status', 'vector_id']);
        });
    }
};
