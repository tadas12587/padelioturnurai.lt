<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table
        Schema::create('sponsor_tournament', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Add is_general column to sponsors
        Schema::table('sponsors', function (Blueprint $table) {
            $table->boolean('is_general')->default(false)->after('tournament_id');
        });

        // Migrate existing data: if tournament_id is null, mark as general
        DB::table('sponsors')->whereNull('tournament_id')->update(['is_general' => true]);

        // Migrate existing sponsor-tournament relationships to pivot table
        $sponsors = DB::table('sponsors')->whereNotNull('tournament_id')->get();
        foreach ($sponsors as $sponsor) {
            DB::table('sponsor_tournament')->insert([
                'sponsor_id' => $sponsor->id,
                'tournament_id' => $sponsor->tournament_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Remove tournament_id from sponsors
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropForeign(['tournament_id']);
            $table->dropColumn('tournament_id');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->foreignId('tournament_id')->nullable()->nullOnDelete()->after('category');
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn('is_general');
        });

        Schema::dropIfExists('sponsor_tournament');
    }
};
