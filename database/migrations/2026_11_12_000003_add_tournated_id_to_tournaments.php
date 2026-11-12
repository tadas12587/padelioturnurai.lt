<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // play.padel.lt/tournament/{id} — set it and the site syncs the
            // schedule, results and notifications for this tournament itself.
            $table->string('tournated_id')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropUnique(['tournated_id']);
            $table->dropColumn('tournated_id');
        });
    }
};
