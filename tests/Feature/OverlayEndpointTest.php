<?php

namespace Tests\Feature;

use App\Models\Overlay;
use App\Models\OverlaySnapshot;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlayEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_overlay_assigns_token_and_defaults(): void
    {
        $overlay = Overlay::create(['name' => 'Test', 'type' => 'group_standings']);

        $this->assertNotEmpty($overlay->token);
        $this->assertSame(8, strlen($overlay->token));
        $this->assertSame('#C9A84C', $overlay->config['colors']['accent']);
        $this->assertNull($overlay->state['active_window_id']);
        $this->assertSame([], $overlay->windows);
    }

    public function test_data_endpoint_404_for_unknown_token(): void
    {
        $this->getJson('/overlay/nope1234/data')->assertNotFound();
    }

    public function test_data_hidden_when_no_active_window(): void
    {
        $overlay = Overlay::create(['name' => 'G', 'type' => 'group_standings']);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => false]);
    }

    public function test_data_hidden_when_active_window_missing(): void
    {
        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'windows' => [],
            'state' => ['active_window_id' => 'ghost', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => false]);
    }

    public function test_data_returns_active_window_groups(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10229',
            'payload' => [
                'groups_by_category' => [
                    '47817' => [['id' => 5, 'name' => 'A', 'entries' => [], 'matches' => []]],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'tournament_external_id' => '10229',
            'windows' => [[
                'id' => 'w1', 'type' => 'groups', 'name' => 'W1',
                'subgroups' => [['category_id' => 47817, 'group_id' => null]],
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => 'Next'],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson([
                'visible' => true,
                'window_id' => 'w1',
                'window_type' => 'groups',
                'subgroup_count' => 1,
                'next_match' => 'Next',
            ]);
    }

    public function test_data_stale_when_active_window_has_no_snapshot(): void
    {
        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'tournament_external_id' => '10229',
            'windows' => [[
                'id' => 'w1', 'type' => 'groups', 'name' => 'W1',
                'subgroups' => [['category_id' => 47817, 'group_id' => null]],
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'groups' => [], 'stale' => true]);
    }

    public function test_data_returns_sponsors_window(): void
    {
        $sponsor = \App\Models\Sponsor::create(['name' => 'A', 'logo' => 'sponsors/a.png', 'url' => 'https://a.lt', 'category' => 'gold', 'is_active' => true]);

        $overlay = Overlay::create([
            'name' => 'S', 'type' => 'group_standings',
            'windows' => [[
                'id' => 'w1', 'type' => 'sponsors', 'name' => 'Rėmėjai',
                'variant' => 'bar', 'rotate_seconds' => 8,
                'sponsor_ids' => [$sponsor->id], 'images' => [],
                'scrim_enabled' => true, 'scrim_opacity' => 40,
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson([
                'visible' => true,
                'window_type' => 'sponsors',
                'variant' => 'bar',
                'rotate_seconds' => 8,
                'scrim' => ['enabled' => true, 'opacity' => 40],
            ])
            ->assertJsonPath('items.0.name', 'A');
    }

    public function test_wanted_rejects_without_token(): void
    {
        config(['services.overlay.ingest_token' => 'secret']);

        $this->getJson('/overlay/wanted')->assertStatus(403);
        $this->getJson('/overlay/wanted', ['X-Overlay-Token' => 'wrong'])->assertStatus(403);
    }

    public function test_wanted_returns_distinct_tournament_ids(): void
    {
        config(['services.overlay.ingest_token' => 'secret']);

        Overlay::create(['name' => 'A', 'type' => 'group_standings', 'tournament_external_id' => '10424']);
        Overlay::create(['name' => 'B', 'type' => 'group_standings', 'tournament_external_id' => '10424']);
        Overlay::create(['name' => 'C', 'type' => 'group_standings', 'tournament_external_id' => '99999']);
        Overlay::create(['name' => 'D', 'type' => 'group_standings', 'tournament_external_id' => null]);

        $res = $this->getJson('/overlay/wanted', ['X-Overlay-Token' => 'secret'])->assertOk();

        $ids = $res->json('tournament_ids');
        sort($ids);
        $this->assertSame(['10424', '99999'], $ids);
    }

    public function test_bracket_window_returns_category_draw_from_snapshot(): void
    {
        \App\Models\OverlaySnapshot::create([
            'tournament_external_id' => '10424',
            'payload' => [
                'brackets_by_category' => [
                    '53642' => [
                        'rounds' => [
                            ['title' => 'Finalas', 'matches' => [
                                ['team1' => 'A', 'team2' => 'C', 'sets1' => '6', 'sets2' => '2', 'winner' => 1, 'court' => 'Kortas 2', 'time' => '10:00'],
                            ]],
                        ],
                        'third' => ['team1' => 'B', 'team2' => 'D', 'sets1' => '', 'sets2' => '', 'winner' => 2],
                        'placements' => [
                            ['title' => 'Dėl 7 vietos', 'rounds' => [
                                ['title' => '', 'matches' => [
                                    ['team1' => 'E', 'team2' => 'F', 'sets1' => '', 'sets2' => '', 'winner' => null],
                                ]],
                            ]],
                        ],
                    ],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'B', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'bracket', 'name' => 'T', 'category_id' => 53642]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'bracket'])
            ->assertJsonPath('bracket.rounds.0.matches.0.court', 'Kortas 2')
            ->assertJsonPath('bracket.rounds.0.matches.0.time', '10:00')
            ->assertJsonPath('bracket.third.team1', 'B')
            ->assertJsonPath('bracket.placements.0.title', 'Dėl 7 vietos');
    }
}
