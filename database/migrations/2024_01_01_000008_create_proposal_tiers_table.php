<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->string('price_suffix')->nullable()->comment('e.g. "/ mėn." or "nuo"');
            $table->json('benefits')->nullable();
            $table->boolean('highlighted')->default(false);
            $table->unsignedInteger('slots_total')->nullable();
            $table->unsignedInteger('slots_taken')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_tiers');
    }
};
