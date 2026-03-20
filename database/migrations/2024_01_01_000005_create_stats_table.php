<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->integer('value')->default(0);
            $table->string('label_lt');
            $table->string('label_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats');
    }
};
