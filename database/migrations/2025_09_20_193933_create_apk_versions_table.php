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
        Schema::create('apk_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('filename');
            $table->string('path');
            $table->bigInteger('download_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('changelog')->nullable();
            $table->timestamps();

            $table->index(['is_active']);
            $table->index('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apk_versions');
    }
};
