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
        Schema::table('player_logs', function (Blueprint $table) {
            $table->string('event_type')->nullable()->after('tenant_id');
            $table->json('event_data')->nullable()->after('event_type');
            $table->unsignedBigInteger('media_file_id')->nullable()->after('event_data');
            $table->timestamp('timestamp')->nullable()->after('media_file_id');
            $table->string('ip_address')->nullable()->after('timestamp');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->timestamp('updated_at')->nullable()->after('created_at');

            $table->foreign('media_file_id')->references('id')->on('media_files')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_logs', function (Blueprint $table) {
            $table->dropForeign(['media_file_id']);
            $table->dropColumn([
                'event_type',
                'event_data',
                'media_file_id',
                'timestamp',
                'ip_address',
                'user_agent',
                'updated_at'
            ]);
        });
    }
};
