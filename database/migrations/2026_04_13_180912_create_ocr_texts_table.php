<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_id')
                ->constrained('archives')
                ->cascadeOnDelete();
            $table->longText('extracted_text');
            $table->string('vector_id', 36)->unique(); // bentuknya uuid
            $table->timestamps();

            $table->unique('archive_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_texts');
    }
};
