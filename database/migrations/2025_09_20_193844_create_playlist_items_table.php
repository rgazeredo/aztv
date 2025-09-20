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
        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
            $table->foreignId('media_file_id')->constrained()->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->integer('display_time_override')->nullable(); // override display time for this specific item
            $table->timestamps();

            $table->index(['playlist_id', 'order']);
            $table->unique(['playlist_id', 'order']); // ensure unique order within playlist
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_items');
    }
};
