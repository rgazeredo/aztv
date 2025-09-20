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
        Schema::table('media_files', function (Blueprint $table) {
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending')
                  ->after('status');
            $table->timestamp('processed_at')->nullable()->after('processing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn(['processing_status', 'processed_at']);
        });
    }
};
