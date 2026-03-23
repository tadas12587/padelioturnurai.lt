<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Results: text content or external URL (separate from groups/tables URL)
            $table->text('results_text')->nullable()->after('results_url');
            $table->string('results_link')->nullable()->after('results_text');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['results_text', 'results_link']);
        });
    }
};
