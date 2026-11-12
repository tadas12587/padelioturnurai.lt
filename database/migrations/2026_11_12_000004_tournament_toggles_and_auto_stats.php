<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // what a Tournated-linked tournament shows; each can be switched off
            $table->boolean('sync_enabled')->default(true)->after('tournated_id');
            $table->boolean('show_schedule')->default(true)->after('sync_enabled');
            $table->boolean('push_enabled')->default(true)->after('show_schedule');
            // participants_count is filled from Tournated unless typed by hand
            $table->boolean('participants_auto')->default(true)->after('participants_count');
        });

        Schema::table('stats', function (Blueprint $table) {
            // auto = computed from tournaments (+ base_value for history before the site); manual = value as typed
            $table->string('mode', 10)->default('manual')->after('value');
            $table->integer('base_value')->default(0)->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['sync_enabled', 'show_schedule', 'push_enabled', 'participants_auto']);
        });
        Schema::table('stats', function (Blueprint $table) {
            $table->dropColumn(['mode', 'base_value']);
        });
    }
};
