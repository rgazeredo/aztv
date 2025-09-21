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
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('category', 50); // theme, player_defaults, notifications, etc.
            $table->string('key', 100); // specific setting key
            $table->text('value'); // setting value (JSON for complex types)
            $table->enum('type', ['string', 'integer', 'boolean', 'json', 'array', 'float'])->default('string');
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'key']);
            $table->unique(['tenant_id', 'category', 'key'], 'tenant_category_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};