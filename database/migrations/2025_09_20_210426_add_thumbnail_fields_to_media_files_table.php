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
            $table->string('thumbnail_small')->nullable()->after('thumbnail_path');
            $table->string('thumbnail_medium')->nullable()->after('thumbnail_small');
            $table->string('thumbnail_large')->nullable()->after('thumbnail_medium');
            $table->timestamp('thumbnails_generated_at')->nullable()->after('thumbnail_large');

            if (!Schema::hasIndex('media_files', 'media_files_mime_type_index')) {
                $table->index('mime_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex(['mime_type']);
            $table->dropColumn(['thumbnail_small', 'thumbnail_medium', 'thumbnail_large', 'thumbnails_generated_at']);
        });
    }
};
