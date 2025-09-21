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
        Schema::create('playlist_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('days_of_week')->nullable(); // Array of integers [0-6] where 0 = Sunday
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1); // Higher number = higher priority
            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'is_active']);
            $table->index(['playlist_id']);
            $table->index(['start_date', 'end_date']);
            $table->index(['start_time', 'end_time']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_schedules');
    }
};
