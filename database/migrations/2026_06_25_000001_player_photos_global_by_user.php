<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the player library global: identify a player by their Tournated user id
 * so the same person across tournaments is one row (shared photo/country/…).
 * Drops the per-tournament uniqueness.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_photos', function (Blueprint $table) {
            $table->unsignedBigInteger('tournated_user_id')->nullable()->after('person_key')->index();
        });

        Schema::table('player_photos', function (Blueprint $table) {
            $table->dropUnique(['tournament_external_id', 'person_key']);
        });

        // Global players aren't tied to one tournament.
        Schema::table('player_photos', function (Blueprint $table) {
            $table->string('tournament_external_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('player_photos', function (Blueprint $table) {
            $table->unique(['tournament_external_id', 'person_key']);
            $table->dropIndex(['tournated_user_id']);
            $table->dropColumn('tournated_user_id');
        });
    }
};
