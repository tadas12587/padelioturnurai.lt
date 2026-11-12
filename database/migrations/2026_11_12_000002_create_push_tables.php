<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A phone that asked to be told when "its" player is up, per tournament.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tournament_external_id')->index();
            $table->string('player');
            $table->string('player_key')->index(); // lower-case, no diacritics
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();
        });

        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('dedupe_key');
            $table->string('kind', 20);
            $table->string('title');
            $table->string('body', 500);
            $table->string('url', 500);
            $table->string('status', 10)->default('pending'); // pending | sent | failed | expired
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['push_subscription_id', 'dedupe_key']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_messages');
        Schema::dropIfExists('push_subscriptions');
    }
};
