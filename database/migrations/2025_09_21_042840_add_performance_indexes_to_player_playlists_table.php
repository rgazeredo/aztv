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
        Schema::table('player_playlists', function (Blueprint $table) {
            // Index for sync queries ordered by creation date
            $table->index(['player_id', 'created_at'], 'idx_player_playlists_sync');

            // Index for priority ordering
            $table->index(['player_id', 'priority'], 'idx_player_playlists_priority');

            // Index for date-based playlist queries
            $table->index(['start_date', 'end_date'], 'idx_player_playlists_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_playlists', function (Blueprint $table) {
            $table->dropIndex('idx_player_playlists_sync');
            $table->dropIndex('idx_player_playlists_priority');
            $table->dropIndex('idx_player_playlists_dates');
        });
    }
};
