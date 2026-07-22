<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manually imported entry lists (from an Excel export) — pairs per category
 * for a tournament, so the draw can run before Tournated has matches. Keyed
 * by tournament; overrides the scraped participants until the draw is done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_lists', function (Blueprint $table) {
            $table->id();
            $table->string('tournament_external_id')->unique();
            $table->json('data')->nullable();       // { normCategoryName: [ {id,name,seed,...} ] }
            $table->string('source_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_lists');
    }
};
