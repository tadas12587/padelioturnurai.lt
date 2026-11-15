<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // [{"name": "Vidus", "courts": [52836, 52837]}, ...] — one entry per venue TV (/tv/1, /tv/2 …)
            $table->json('tv_screens')->nullable()->after('tv_courts');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('tv_screens');
        });
    }
};
