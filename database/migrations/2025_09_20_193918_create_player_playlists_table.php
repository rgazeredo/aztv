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
        Schema::create('player_playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
            $table->integer('priority')->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('schedule_config')->nullable(); // stores schedule configuration (times, days of week, etc.)
            $table->timestamps();

            $table->index(['player_id', 'priority']);
            $table->index(['playlist_id']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_playlists');
    }
};
