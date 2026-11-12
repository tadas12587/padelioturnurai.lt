<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournated_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('tournament_external_id')->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_source')->nullable();
            $table->unsignedInteger('match_count')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournated_syncs');
    }
};
