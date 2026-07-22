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
            'state' => ['active_window_id' => 'w1', 'next_match' => '', 'h2h_match_id' => 99, 'h2h_show_score' => true],
        ]);
        \App\Models\TournamentScore::put('10424', ['teams' => [['A B'], ['C D']], 'sets' => [], 'games' => [3, 2], 'points' => [3, 1],
            'adv' => null, 'star_stage' => 0, 'tiebreak' => false, 'super_tiebreak' => false, 'tb' => [0, 0], 'server_team' => 0,
            'status' => 'playing', 'winner' => null], 99);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJsonPath('h2h.live_score.found', true)
            ->assertJsonPath('h2h.live_score.teams.0.games', 3)
            ->assertJsonPath('h2h.live_score.teams.0.point', '40');
    }

    public function test_h2h_returns_animated_background_config(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [['id' => 99, 'category' => 'X', 'team1' => ['A B'], 'team2' => ['C D']]],
        ]]);
        $overlay = Overlay::create([
            'name' => 'H', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'h2h', 'name' => 'Akistata',
                'h2h_bg_mode' => 'image', 'h2h_bg_intensity' => 'medium', 'h2h_bg_count' => 12]],
            'state' => ['active_window_id' => 'w1', 'next_match' => '', 'h2h_match_id' => 99],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJsonPath('h2h.bg.mode', 'image')
            ->assertJsonPath('h2h.bg.intensity', 'medium')
            ->assertJsonPath('h2h.bg.count', 12);
    }

    public function test_toggle_score_auto_loads_scorer_with_h2h_pair(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [['id' => 99, 'team1' => ['A B'], 'team2' => ['C D'], 'category' => 'X']],
        ]]);
        $overlay = Overlay::create([
            'name' => 'H', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [
                ['id' => 'w1', 'type' => 'h2h', 'name' => 'Akistata'],
                ['id' => 'w2', 'type' => 'score', 'name' => 'Rez'],
            ],
            'state' => ['active_window_id' => 'w1', 'next_match' => '', 'h2h_match_id' => 99],
        ]);

        \Livewire\Livewire::test(\App\Filament\Pages\H2hControlPage::class)
            ->set('overlayId', $overlay->id)->set('windowId', 'w1')
            ->call('toggleScore');

        $overlay->refresh();
        $this->assertTrue($overlay->state['h2h_show_score']);
        $this->assertSame('99', \App\Models\TournamentScore::matchFor('10424'));
        $this->assertSame([['A B'], ['C D']], \App\Models\TournamentScore::stateFor('10424')['teams']);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJsonPath('h2h.live_score.found', true)
            ->assertJsonPath('h2h.live_score.teams.0.games', 0);
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

    public function test_load_people_fills_country_from_nation(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'participants_by_category' => ['1' => [['id' => 'r1', 'name' => 'Jonas Petraitis / Adam Kowalski']]],
            'people' => [
                ['name' => 'Jonas Petraitis', 'nation' => 'LT'],
                ['name' => 'Adam Kowalski', 'nation' => 'PL'],
            ],
            'matches' => [],
        ]]);

        \App\Filament\Resources\PlayerPhotoResource::loadPeople('10424');

        $this->assertSame('LT', PlayerPhoto::where('person_key', 'jonas petraitis')->value('country'));
        $this->assertSame('PL', PlayerPhoto::where('person_key', 'adam kowalski')->value('country'));

        // manual country is not overwritten on re-run
        PlayerPhoto::where('person_key', 'jonas petraitis')->update(['country' => 'Lietuva']);
        \App\Filament\Resources\PlayerPhotoResource::loadPeople('10424');
        $this->assertSame('Lietuva', PlayerPhoto::where('person_key', 'jonas petraitis')->value('country'));
    }

    public function test_players_are_shared_across_tournaments_by_user_id(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => 'AAA', 'payload' => [
            'participants_by_category' => ['1' => [['id' => 'r1', 'name' => 'Jonas Petraitis / Antanas Kazlauskas']]],
            'people' => [['id' => 500, 'name' => 'Jonas Petraitis', 'nation' => 'LT'], ['id' => 501, 'name' => 'Antanas Kazlauskas', 'nation' => 'LT']],
            'matches' => [],
        ]]);
        \App\Filament\Resources\PlayerPhotoResource::loadPeople('AAA');
        PlayerPhoto::where('tournated_user_id', 500)->update(['photo' => 'player-photos/j.gif']);

        // Same player (id 500) in another tournament, spelled slightly differently.
        OverlaySnapshot::create(['tournament_external_id' => 'BBB', 'payload' => [
            'participants_by_category' => ['9' => [['id' => 'r9', 'name' => 'Jonas Petraitis / Zigmas Wanderis']]],
            'people' => [['id' => 500, 'name' => 'Jonas Petraitis', 'nation' => 'LT'], ['id' => 777, 'name' => 'Zigmas Wanderis', 'nation' => 'PL']],
            'matches' => [],
        ]]);
        \App\Filament\Resources\PlayerPhotoResource::loadPeople('BBB');

        // Still ONE card for player 500, keeping its photo.
        $this->assertSame(1, PlayerPhoto::where('tournated_user_id', 500)->count());
        $this->assertSame('player-photos/j.gif', PlayerPhoto::where('tournated_user_id', 500)->value('photo'));
    }

    public function test_h2h_finds_photo_by_user_id(): void
    {
        PlayerPhoto::create(['tournament_external_id' => 'AAA', 'tournated_user_id' => 500, 'person_key' => 'jonas petraitis', 'name' => 'Jonas Petraitis', 'gender' => 'V', 'photo' => 'player-photos/j.gif', 'country' => 'LT']);
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [[
                'id' => 5, 'category' => 'Vyrai', 'team1' => ['Kitoks Vardas'], 'team2' => ['C D'],
                'players1' => [['id' => 500, 'name' => 'Kitoks Vardas', 'nation' => 'LT']],
                'players2' => [['id' => 999, 'name' => 'C D', 'nation' => null]],
            ]],
        ]]);
        $overlay = Overlay::create([
            'name' => 'H', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'h2h', 'name' => 'Akistata']],
            'state' => ['active_window_id' => 'w1', 'next_match' => '', 'h2h_match_id' => 5],
        ]);

        // Even though the match name differs, the photo is found by user id 500.
        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJsonPath('h2h.team1.0.is_stock', false)
            ->assertJsonPath('h2h.team1.0.country', 'LT');
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
        $this->assertContains('w1', \App\Models\Overlay::activeIds($overlay->state));
    }
}
