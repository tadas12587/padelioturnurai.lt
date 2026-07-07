<?php

namespace Tests\Feature;

use App\Models\Overlay;
use App\Models\OverlaySnapshot;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_abbrev_name(): void
    {
        $d = app(OverlayData::class);
        $this->assertSame('T. Šeškauskas', $d->abbrevName('Tadas Šeškauskas'));
        $this->assertSame('J. Grigorenko', $d->abbrevName('Jevgenij Grigorenko'));
    }

    public function test_resolve_score_builds_card(): void
    {
        $d = app(OverlayData::class);
        $window = ['score_position' => 'top-left', 'score_width' => 520, 'show_level' => true];
        $state = [
            'teams' => [['Tadas Šeškauskas', 'Jonas Petraitis'], ['Adam Kowalski', 'Marius Šernius']],
            'sets' => [[6, 4]], 'sets_won' => [1, 0], 'games' => [3, 2], 'points' => [3, 1],
            'adv' => null, 'star_stage' => 0, 'tiebreak' => false, 'super_tiebreak' => false, 'tb' => [0, 0],
            'server_team' => 0, 'status' => 'playing', 'winner' => null,
        ];

        $card = $d->resolveScore($window, $state, ['category' => 'Vyrai Master', 'court' => 'Kortas 1', 'round' => 'Pusfinalis'], $d->scoreConfig($window));

        $this->assertSame('T. Šeškauskas / J. Petraitis', $card['teams'][0]['name']);
        $this->assertSame([6], $card['teams'][0]['sets']);
        $this->assertSame(3, $card['teams'][0]['games']);
        $this->assertSame('40', $card['teams'][0]['point']);
        $this->assertSame('15', $card['teams'][1]['point']);
        $this->assertTrue($card['teams'][0]['serving']);
        $this->assertSame('Vyrai Master', $card['level']);
    }

    public function test_star_advantages_show_1ad_2ad(): void
    {
        $d = app(OverlayData::class);
        $window = ['score_deuce_mode' => 'star'];
        $cfg = $d->scoreConfig($window);
        $base = ['teams' => [['A'], ['B']], 'sets' => [], 'games' => [0, 0], 'points' => [3, 3],
            'tiebreak' => false, 'tb' => [0, 0], 'server_team' => 0, 'status' => 'playing', 'winner' => null];

        $s1 = array_merge($base, ['adv' => 0, 'star_stage' => 'adv1']);
        $this->assertSame('1AD', $d->resolveScore($window, $s1, [], $cfg)['teams'][0]['point']);

        $s2 = array_merge($base, ['adv' => 1, 'star_stage' => 'adv2']);
        $this->assertSame('2AD', $d->resolveScore($window, $s2, [], $cfg)['teams'][1]['point']);
    }

    public function test_data_returns_score_card(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [['id' => 7, 'category' => 'Vyrai Master', 'court' => 'Kortas 1', 'round' => 'Finalas',
                'team1' => ['Tadas Šeškauskas', 'Jonas Petraitis'], 'team2' => ['Adam Kowalski', 'Marius Šernius']]],
        ]]);
        $overlay = Overlay::create([
            'name' => 'S', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'score', 'name' => 'Rezultatas', 'score_deuce_mode' => 'star']],
            'state' => ['active_window_id' => 'w1', 'next_match' => '', 'score_match_id' => 7,
                'score' => ['teams' => [['Tadas Šeškauskas', 'Jonas Petraitis'], ['Adam Kowalski', 'Marius Šernius']],
                    'sets' => [], 'sets_won' => [0, 0], 'games' => [1, 0], 'points' => [2, 0], 'adv' => null, 'star_stage' => 0,
                    'tiebreak' => false, 'super_tiebreak' => false, 'tb' => [0, 0], 'server_team' => 0, 'status' => 'playing', 'winner' => null]],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'score'])
            ->assertJsonPath('score.found', true)
            ->assertJsonPath('score.teams.0.name', 'T. Šeškauskas / J. Petraitis')
            ->assertJsonPath('score.level', 'Vyrai Master');
    }

    public function test_point_and_undo_via_control(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [['id' => 7, 'team1' => ['A B'], 'team2' => ['C D'], 'category' => 'X']],
        ]]);
        $overlay = Overlay::create([
            'name' => 'S', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'score', 'name' => 'Rezultatas', 'score_deuce_mode' => 'star']],
            'state' => ['active_window_id' => null, 'next_match' => ''],
        ]);

        $c = \Livewire\Livewire::test(\App\Filament\Pages\ScoreControlPage::class)
            ->set('overlayId', $overlay->id)->set('windowId', 'w1')
            ->call('selectMatch', 7)
            ->call('point', 0);

        $this->assertSame([1, 0], $overlay->fresh()->state['score']['points']);

        $c->call('undo');
        $this->assertSame([0, 0], $overlay->fresh()->state['score']['points']);
    }
}
