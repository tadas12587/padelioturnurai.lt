<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // group_standings | bracket
            $table->string('token')->unique();
            $table->string('tournament_external_id')->nullable();
            $table->json('config')->nullable();
            $table->json('state')->nullable();
            $table->json('bracket_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlays');
    }
};
