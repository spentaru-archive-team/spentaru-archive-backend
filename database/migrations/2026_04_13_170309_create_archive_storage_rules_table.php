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
        Schema::create('archive_storage_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('archive_categories')
                ->nullOnDelete();

            $table->foreignId('subcategory_id')
                ->nullable()
                ->constrained('subcategories')
                ->nullOnDelete();

            $table->foreignId('cabinet_id')
                ->constrained('cabinets')
                ->cascadeOnDelete();

            $table->unsignedInteger('priority');

            $table->unsignedBigInteger('subcategory_unique_key')->default(0);

            $table->timestamps();

            $table->index(
                ['category_id', 'subcategory_id', 'priority'],
                'idx_storage_rules_category_subcategory_priority'
            );

            $table->unique(
                ['category_id', 'subcategory_unique_key', 'priority'],
                'uniq_storage_rules_category_subcategory_priority'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archive_storage_rules');
    }
};
