<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_tiers', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('tagline_en')->nullable()->after('tagline');
            $table->json('benefits_en')->nullable()->after('benefits');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_tiers', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'tagline_en', 'benefits_en']);
        });
    }
};
