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
        Schema::create('player_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->string('setting_key', 100); // volume, media_interval, loop_enabled, access_password, visual_theme
            $table->text('setting_value'); // setting value (JSON for complex types)
            $table->enum('setting_type', ['string', 'integer', 'boolean', 'json', 'float'])->default('string');
            $table->boolean('is_inherited')->default(false); // whether this setting inherits from tenant defaults
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['player_id', 'setting_key']);
            $table->unique(['player_id', 'setting_key'], 'player_setting_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_settings');
    }
};