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
        Schema::create('content_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['weather', 'quotes', 'currency', 'health_tips', 'funny_videos', 'price_table']);
            $table->boolean('is_enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'is_enabled']);
            $table->unique(['tenant_id', 'type']); // each tenant can have only one module of each type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_modules');
    }
};
