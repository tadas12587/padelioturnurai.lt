<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live scoreboard used to be shared by the whole tournament (one row per
 * tournament_external_id) — fine for several windows of the SAME overlay
 * showing the same match, but it also meant two independent score windows
 * (e.g. two different overlays, two courts) collided into one shared score.
 * Scope it per score window instead: (tournament_external_id, window_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tournament_scores', 'window_id')) {
            Schema::table('tournament_scores', function (Blueprint $table) {
                $table->string('window_id')->nullable()->after('tournament_external_id');
            });
        }

        Schema::table('tournament_scores', function (Blueprint $table) {
            $table->dropUnique(['tournament_external_id']);
            $table->unique(['tournament_external_id', 'window_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_scores', function (Blueprint $table) {
            $table->dropUnique(['tournament_external_id', 'window_id']);
        });
        // A prior row per (tid, window_id) would collide going back to a single
        // tid-only unique constraint; keep only the most recent row per tid.
        $latestIds = \App\Models\TournamentScore::query()
            ->selectRaw('MAX(id) as id')->groupBy('tournament_external_id')->pluck('id');
        \App\Models\TournamentScore::whereNotIn('id', $latestIds)->delete();

        Schema::table('tournament_scores', function (Blueprint $table) {
            $table->unique('tournament_external_id');
            $table->dropColumn('window_id');
        });
    }
};
