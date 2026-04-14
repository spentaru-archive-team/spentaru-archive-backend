<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_id')->constrained('archives')->cascadeOnDelete();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('file_type', 20)->nullable();
            $table->string('file_url');
            $table->timestamps();

            $table->index('archive_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_files');
    }
};
