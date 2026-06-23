<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_photos', function (Blueprint $table) {
            $table->string('rating_type')->nullable();
            $table->string('rating_points')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('player_photos', function (Blueprint $table) {
            $table->dropColumn(['rating_type', 'rating_points', 'country', 'city']);
        });
    }
};
