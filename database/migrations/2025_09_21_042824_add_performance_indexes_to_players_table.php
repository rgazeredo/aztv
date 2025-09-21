<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $column): bool
    {
        $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexdef LIKE ?", [$table, "%$column%"]);
        return !empty($indexes);
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Index for active players by tenant queries
            $table->index(['tenant_id', 'status', 'last_seen'], 'idx_players_tenant_status_seen');

            // Index for last seen queries (online/offline status) - skip if already exists
            if (!$this->indexExists('players', 'last_seen')) {
                $table->index(['last_seen'], 'idx_players_last_seen');
            }

            // Index for status queries
            $table->index(['status'], 'idx_players_status');

            // Note: activation_token index already exists from create migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex('idx_players_tenant_status_seen');
            if ($this->indexExists('players', 'last_seen')) {
                $table->dropIndex('idx_players_last_seen');
            }
            $table->dropIndex('idx_players_status');
        });
    }
};
