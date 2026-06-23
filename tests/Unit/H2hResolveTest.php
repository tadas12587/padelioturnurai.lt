<?php

namespace Tests\Unit;

use App\Models\OverlaySnapshot;
use App\Models\PlayerPhoto;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class H2hResolveTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'participants_by_category' => ['53636' => [
                ['id' => 'r1', 'name' => 'Jonas Petraitis / Antanas Kazlauskas'],
                ['id' => 'r2', 'name' => 'Garcia Lopez / Marius Šernius'],
            ]],
            'matches' => [[
                'id' => 99, 'date' => '2026-04-18', 'time' => '20:00', 'court' => 'Kortas 2',
                'category' => 'Vyrai A', 'round' => 'Pusfinalis', 'status' => 'pending',
                'in_progress' => false, 'score' => null,
                'team1' => ['Jonas Petraitis', 'Antanas Kazlauskas'],
                'team2' => ['Garcia Lopez', 'Marius Šernius'],
            ]],
        ]]);
    }

    public function test_participants_people_splits_pairs(): void
    {
        $this->snapshot();
        $people = app(OverlayData::class)->participantsPeople('10424');

        $this->assertContains('Jonas Petraitis', $people);
        $this->assertContains('Marius Šernius', $people);
        $this->assertCount(4, $people);
    }

    public function test_resolve_h2h_joins_photos_and_stock(): void
    {
        $this->snapshot();
        Storage::fake('public');
        PlayerPhoto::create([
            'tournament_external_id' => '10424', 'person_key' => 'jonas petraitis',
            'name' => 'Jonas Petraitis', 'gender' => 'V', 'photo' => 'player-photos/j.gif',
            'rating_type' => 'LTU', 'rating_points' => '1234', 'country' => 'Lietuva', 'city' => 'Vilnius',
        ]);

        $h = app(OverlayData::class)->resolveH2h('10424', 99, []);

        $this->assertTrue($h['found']);
        $this->assertSame('Jonas Petraitis', $h['team1'][0]['name']);
        $this->assertStringContainsString('player-photos/j.gif', $h['team1'][0]['photo']);
        $this->assertFalse($h['team1'][0]['is_stock']);
        $this->assertSame('1234', $h['team1'][0]['rating_points']);
        $this->assertSame('Vilnius', $h['team1'][0]['city']);
        // No photo for the partner → male stock (category "Vyrai A").
        $this->assertTrue($h['team1'][1]['is_stock']);
        $this->assertStringContainsString('player-male', $h['team1'][1]['photo']);
        $this->assertSame('20:00', $h['center']['time']);
        $this->assertSame('Kortas 2', $h['center']['court']);
    }

    public function test_resolve_h2h_missing_match(): void
    {
        $this->snapshot();
        $this->assertFalse(app(OverlayData::class)->resolveH2h('10424', 12345, [])['found']);
    }
}
