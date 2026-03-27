<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 150);
            $table->string('locale', 5)->default('lt');
            $table->timestamps();

            // Same person can register interest only once per tournament
            $table->unique(['tournament_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_interests');
    }
};
