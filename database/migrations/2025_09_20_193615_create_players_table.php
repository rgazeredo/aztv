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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('alias')->nullable();
            $table->string('location')->nullable();
            $table->string('group')->nullable();
            $table->enum('status', ['inactive', 'active', 'offline', 'error'])->default('inactive');
            $table->string('ip_address')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->string('app_version')->nullable();
            $table->json('device_info')->nullable();
            $table->string('activation_token')->unique()->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('activation_token');
            $table->index('last_seen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
