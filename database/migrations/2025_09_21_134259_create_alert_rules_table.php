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
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('type'); // player_offline, playback_error, storage_limit
            $table->json('condition'); // Specific conditions for the alert
            $table->integer('threshold')->nullable(); // Threshold value (minutes for offline, percentage for storage, etc.)
            $table->json('recipients'); // Array of email addresses
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['tenant_id', 'type']);
            $table->index(['is_active', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
