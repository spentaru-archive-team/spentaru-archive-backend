<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('archive_physical_locations', function (Blueprint $table) {
            $table->fullText(['label_code', 'notes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_physical_locations', function (Blueprint $table) {
            $table->dropFullText(['label_code', 'notes']);
        });
    }
};
