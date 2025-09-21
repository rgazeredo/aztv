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
        Schema::table('playlist_items', function (Blueprint $table) {
            // Index for ordered media items in playlist
            $table->index(['playlist_id', 'order'], 'idx_playlist_items_order');

            // Index for media file lookups
            $table->index(['media_file_id'], 'idx_playlist_items_media');

            // Compound index for playlist sync queries
            $table->index(['playlist_id', 'created_at'], 'idx_playlist_items_sync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playlist_items', function (Blueprint $table) {
            $table->dropIndex('idx_playlist_items_order');
            $table->dropIndex('idx_playlist_items_media');
            $table->dropIndex('idx_playlist_items_sync');
        });
    }
};
