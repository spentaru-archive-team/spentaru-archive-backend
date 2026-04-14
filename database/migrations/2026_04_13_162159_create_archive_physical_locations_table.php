<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_physical_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_id')->constrained('archives')->cascadeOnDelete();
            $table->foreignId('cabinet_id')->constrained('cabinets')->restrictOnDelete();
            $table->foreignId('rack_id')->constrained('racks')->restrictOnDelete();
            $table->unsignedInteger('slot_number');
            $table->string('label_code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();


            $table->unique('archive_id');
            $table->unique(['rack_id', 'slot_number']);
            $table->index(['cabinet_id', 'rack_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_physical_locations');
    }
};
