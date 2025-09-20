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
        Schema::create('player_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['info', 'warning', 'error', 'heartbeat', 'media_played', 'command_executed']);
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('created_at');

            $table->index(['player_id', 'type']);
            $table->index(['player_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_logs');
    }
};
