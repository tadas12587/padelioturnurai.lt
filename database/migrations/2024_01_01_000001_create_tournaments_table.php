<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->enum('status', ['upcoming', 'active', 'past']);
            $table->date('date_start');
            $table->date('date_end')->nullable();
            $table->string('location');
            $table->integer('participants_count')->default(0);
            $table->string('registration_url')->nullable();
            $table->boolean('registration_active')->default(false);
            $table->string('cover_image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
