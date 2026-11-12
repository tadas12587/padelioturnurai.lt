<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A phone with the "Padelio turnyrai" app: one tournament at a time,
        // either as a player (me) or a spectator following some players.
        Schema::create('app_devices', function (Blueprint $table) {
            $table->id();
            $table->string('expo_token')->unique();
            $table->string('platform', 10)->nullable();
            $table->string('tournament_external_id')->nullable()->index();
            $table->string('role', 10)->default('spectator'); // player | spectator
            $table->string('me')->nullable();
            $table->string('me_key')->nullable()->index();
            $table->json('follows')->nullable();      // display names
            $table->json('follow_keys')->nullable();  // folded names
            $table->timestamps();
        });

        Schema::table('push_messages', function (Blueprint $table) {
            $table->foreignId('push_subscription_id')->nullable()->change();
            $table->foreignId('app_device_id')->nullable()->after('push_subscription_id')->constrained()->cascadeOnDelete();
            $table->unique(['app_device_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::table('push_messages', function (Blueprint $table) {
            $table->dropUnique(['app_device_id', 'dedupe_key']);
            $table->dropConstrainedForeignId('app_device_id');
        });
        Schema::dropIfExists('app_devices');
    }
};
