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
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type');
            $table->bigInteger('size'); // size in bytes
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->integer('duration')->nullable(); // duration in seconds for videos
            $table->integer('display_time')->default(10); // default display time in seconds
            $table->string('folder')->nullable();
            $table->json('tags')->nullable();
            $table->enum('status', ['processing', 'ready', 'error'])->default('processing');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'folder']);
            $table->index('mime_type');
            $table->index('filename');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
