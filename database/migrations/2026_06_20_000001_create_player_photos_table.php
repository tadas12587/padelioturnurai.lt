<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_photos', function (Blueprint $table) {
            $table->id();
            $table->string('tournament_external_id')->index();
            $table->string('person_key');
            $table->string('name');
            $table->string('gender', 1)->default('V'); // V = male, M = female
            $table->string('photo')->nullable();
            $table->timestamps();
            $table->unique(['tournament_external_id', 'person_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_photos');
    }
};
