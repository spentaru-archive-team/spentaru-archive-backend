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
        Schema::table('archives', function (Blueprint $table) {
            $table->date('retention_due_date')->nullable()->after('uploader');
            $table->enum('retention_status', [
                'active',
                'ready_for_destruction',
                'destroyed',
                'retained',
            ])->default('active')->after('retention_due_date');
            $table->timestamp('retention_decided_at')->nullable()->after('retention_status');
            $table->foreignId('retention_decided_by')->nullable()->after('retention_decided_at')->constrained('users')->nullOnDelete();
            $table->text('retention_note')->nullable()->after('retention_decided_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropForeign(['retention_decided_by']);
            $table->dropColumn([
                'retention_due_date',
                'retention_status',
                'retention_decided_at',
                'retention_decided_by',
                'retention_note',
            ]);
        });
    }
};
