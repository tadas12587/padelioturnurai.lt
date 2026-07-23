<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `names` column was added to the create migration after it had already
 * run on production, so existing databases are missing it. Add it here, guarded
 * so fresh databases (which already have it from the create migration) are fine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('entry_lists', 'names')) {
            Schema::table('entry_lists', function (Blueprint $table) {
                $table->json('names')->nullable()->after('data');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('entry_lists', 'names')) {
            Schema::table('entry_lists', function (Blueprint $table) {
                $table->dropColumn('names');
            });
        }
    }
};
