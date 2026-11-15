<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // TV schedule (/tv/{tournated_id}): which courts, in order ("1,2,3,4"); empty = all of the day
            $table->string('tv_courts', 100)->nullable()->after('push_enabled');
            // under each player: club (club league) or city / country
            $table->boolean('tv_show_place')->default(true)->after('tv_courts');
            // text is sized for the people furthest away: TV diagonal (inches) and that distance (m)
            $table->unsignedTinyInteger('tv_screen_inch')->default(55)->after('tv_show_place');
            $table->decimal('tv_distance_m', 3, 1)->default(2.0)->after('tv_screen_inch');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['tv_courts', 'tv_show_place', 'tv_screen_inch', 'tv_distance_m']);
        });
    }
};
