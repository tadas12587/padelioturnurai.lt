<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One live scoreboard per tournament, shared by every overlay of that
 * tournament — so a result entered once shows everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_scores', function (Blueprint $table) {
            $table->id();
            $table->string('tournament_external_id')->unique();
            $table->string('match_id')->nullable();
            $table->json('state')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_scores');
    }
};
