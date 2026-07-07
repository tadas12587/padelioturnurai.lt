<?php

namespace Tests\Feature;

use App\Models\Overlay;
use App\Models\OverlaySnapshot;
use App\Models\PlayerPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class H2hTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_photo_persists(): void
    {
        $p = PlayerPhoto::create([
            'tournament_external_id' => '10424',
            'person_key' => 'jonas petraitis',
            'name' => 'Jonas Petraitis',
            'gender' => 'V',
            'photo' => 'player-photos/x.gif',
        ]);

        $this->assertDatabaseHas('player_photos', ['person_key' => 'jonas petraitis', 'gender' => 'V']);
        $this->assertSame('Jonas Petraitis', $p->fresh()->name);
    }

    public function test_data_returns_h2h(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [[
                'id' => 99, 'time' => '20:00', 'court' => 'Kortas 2', 'category' => 'Vyrai A',
                'round' => 'Pusfinalis', 'in_progress' => false, 'score' => null,
                'team1' => ['Jonas Petraitis', 'Antanas Kazlauskas'],
                'team2' => ['Garcia Lopez', 'Marius Šernius'],
            ]],
        ]]);

        $overlay = Overlay::create([
            'name' => 'H', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'h2h', 'name' => 'Akistata',
                'h2h_center' => ['time', 'court'], 'h2h_text' => 'VS', 'h2h_animate' => true]],
            'state' => ['active_window_id' => 'w1', 'next_match' => '', 'h2h_match_id' => 99],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'h2h'])
            ->assertJsonPath('h2h.found', true)
            ->assertJsonPath('h2h.team1.0.name', 'Jonas Petraitis')
            ->assertJsonPath('h2h.center.court', 'Kortas 2');
    }

    public function test_h2h_shows_live_score_when_enabled(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [['id' => 99, 'category' => 'X', 'team1' => ['A B'], 'team2' => ['C D']]],
        ]]);
        $overlay = Overlay::create([
            'name' => 'H', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [
                ['id' => 'w1', 'type' => 'h2h', 'name' => 'Akistata', 'category_id' => 0],
                ['id' => 'w2', 'type' => 'score', 'name' => 'Rez', 'score_deuce_mode' => 'star'],
            ],
            'state' => ['active_window_id' => 'w1', 'next_match' => '', 'h2h_match_id' => 99, 'h2h_show_score' => true,
                'score' => ['teams' => [['A B'], ['C D']], 'sets' => [], 'games' => [3, 2], 'points' => [3, 1], 'adv' => null, 'star_stage' => 0,
                    'tiebreak' => false, 'super_tiebreak' => false, 'tb' => [0, 0], 'server_team' => 0, 'status' => 'playing', 'winner' => null]],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJsonPath('h2h.live_score.t1.games', 3)
            ->assertJsonPath('h2h.live_score.t1.point', '40');
    }

    public function test_load_people_upserts_rows_with_gender(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'participants_by_category' => ['53636' => [['id' => 'r1', 'name' => 'Jonas Petraitis / Antanas Kazlauskas']]],
            'matches' => [],
        ]]);

        \App\Filament\Resources\PlayerPhotoResource::loadPeople('10424');

        $this->assertDatabaseHas('player_photos', ['person_key' => 'jonas petraitis', 'name' => 'Jonas Petraitis']);
        $this->assertSame(2, PlayerPhoto::where('tournament_external_id', '10424')->count());
    }

    public function test_show_match_sets_state_and_active_window(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [['id' => 99, 'team1' => ['A B'], 'team2' => ['C D'], 'time' => '20:00']],
        ]]);
        $overlay = Overlay::create([
            'name' => 'H', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'h2h', 'name' => 'Akistata']],
            'state' => ['active_window_id' => null, 'next_match' => ''],
        ]);

        \Livewire\Livewire::test(\App\Filament\Pages\H2hControlPage::class)
            ->set('overlayId', $overlay->id)->set('windowId', 'w1')
            ->call('showMatch', 99);

        $overlay->refresh();
        $this->assertSame(99, $overlay->state['h2h_match_id']);
        $this->assertSame('w1', $overlay->state['active_window_id']);
    }
}
