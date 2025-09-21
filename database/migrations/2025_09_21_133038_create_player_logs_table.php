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
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('event_type');
            $table->json('event_data')->nullable();
            $table->foreignId('media_file_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('timestamp');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['player_id', 'timestamp']);
            $table->index(['tenant_id', 'timestamp']);
            $table->index(['event_type', 'timestamp']);
            $table->index(['media_file_id', 'timestamp']);
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
