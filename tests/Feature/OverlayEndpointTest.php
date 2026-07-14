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

    public function test_groups_window_filters_by_segment_and_labels_it(): void
    {
        $mk = fn (int $id, string $name, ?string $seg) => [
            'id' => $id, 'name' => $name, 'segment' => $seg, 'entries' => [], 'matches' => [],
        ];

        OverlaySnapshot::create([
            'tournament_external_id' => '10229',
            'payload' => [
                'groups_by_category' => [
                    '47817' => [
                        $mk(1, 'A', null),    // main draw (empty segment)
                        $mk(2, 'B', '5-8'),
                        $mk(3, 'C', '9-16'),
                    ],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'tournament_external_id' => '10229',
            'windows' => [[
                'id' => 'w1', 'type' => 'groups', 'name' => 'W1',
                'subgroups' => [['category_id' => 47817, 'segments' => ['5-8', '9-16'], 'group_id' => null]],
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $res = $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'subgroup_count' => 2]);

        $this->assertSame(['B', 'C'], collect($res->json('groups'))->pluck('name')->all());
        $this->assertSame(['5-8', '9-16'], collect($res->json('groups'))->pluck('segment')->all());
    }

    public function test_groups_window_labels_main_when_segment_empty(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10229',
            'payload' => [
                'groups_by_category' => [
                    '47817' => [['id' => 1, 'name' => 'A', 'segment' => null, 'entries' => [], 'matches' => []]],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'tournament_external_id' => '10229',
            'windows' => [[
                'id' => 'w1', 'type' => 'groups', 'name' => 'W1',
                'subgroups' => [['category_id' => 47817, 'segments' => [], 'group_id' => null]],
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJsonPath('groups.0.segment', 'Main');
    }

    public function test_ingest_stores_participants_by_category(): void
    {
        config(['services.overlay.ingest_token' => 'secret']);

        $this->postJson('/overlay/ingest', [
            'tournament_id' => '10424',
            'participants_by_category' => [
                '53636' => [['id' => 1, 'name' => 'Garcia / Lopez', 'seed' => 1]],
            ],
        ], ['X-Overlay-Token' => 'secret'])->assertOk();

        $teams = app(\App\Services\OverlayData::class)->participants('10424', 53636);
        $this->assertSame('Garcia / Lopez', $teams[0]['name']);
        $this->assertSame(1, $teams[0]['seed']);
    }

    public function test_data_returns_draw_window_board(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'title' => 'Padelio Turnyras',
            'participants_by_category' => ['53636' => [['id' => 1, 'name' => 'A / B', 'pot' => 1]]],
        ]]);

        $overlay = Overlay::create([
            'name' => 'D', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [[
                'id' => 'w1', 'type' => 'draw', 'name' => 'Traukimas', 'category_id' => 53636,
                'format' => 'groups', 'group_count' => 2, 'group_size' => 2, 'use_pots' => true,
                'camera_corner' => 'bottom-right', 'sponsor_ids' => [], 'images' => [],
            ]],
            'state' => [
                'active_window_id' => 'w1', 'next_match' => '',
                'draws' => ['w1' => [
                    'teams' => [['id' => 1, 'name' => 'A / B', 'pot' => 1]],
                    'slots' => ['A1' => 1, 'A2' => null, 'B1' => null, 'B2' => null],
                    'current' => ['team_id' => 1, 'slot' => 'A1'], 'history' => [['team_id' => 1, 'slot' => 'A1']],
                    'active_pot' => 1, 'status' => 'idle',
                ]],
            ],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'draw'])
            ->assertJsonPath('draw.format', 'groups')
            ->assertJsonPath('draw.camera_corner', 'bottom-right')
            ->assertJsonPath('draw.board.0.label', 'A')
            ->assertJsonPath('draw.slots.A1.name', 'A / B')
            ->assertJsonPath('draw.current.name', 'A / B')
            ->assertJsonPath('draw.current.slot', 'A1');
    }

    public function test_control_action_play_and_stop(): void
    {
        $overlay = Overlay::create([
            'name' => 'C', 'type' => 'group_standings',
            'windows' => [['id' => 'w1', 'type' => 'groups', 'name' => 'W1']],
            'state' => ['active_window_id' => null, 'next_match' => ''],
        ]);

        $this->postJson("/overlay/{$overlay->token}/control", ['action' => 'play', 'window_id' => 'w1'])
            ->assertOk()->assertJson(['active_window_ids' => ['w1']]);
        $this->assertSame(['w1'], Overlay::activeIds($overlay->fresh()->state));

        $this->postJson("/overlay/{$overlay->token}/control", ['action' => 'stop'])
            ->assertOk()->assertJson(['active_window_ids' => []]);
        $this->assertSame([], Overlay::activeIds($overlay->fresh()->state));
    }

    public function test_sponsor_window_pulls_images_from_a_gallery(): void
    {
        $gallery = \App\Models\Gallery::create(['name' => 'Turnyro rėmėjai', 'images' => ['galleries/a.png', 'galleries/b.png']]);
        $overlay = Overlay::create([
            'name' => 'S', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'sponsors', 'name' => 'Rėmėjai', 'variant' => 'bar', 'gallery_ids' => [$gallery->id]]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJsonPath('window_type', 'sponsors')
            ->assertJsonCount(2, 'items');
    }

    public function test_photowall_window_returns_wall_config(): void
    {
        $gallery = \App\Models\Gallery::create(['name' => 'Rėmėjai', 'images' => ['galleries/a.png', 'galleries/b.png']]);
        $overlay = Overlay::create([
            'name' => 'PW', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'photowall', 'name' => 'Foto sienelė',
                'gallery_ids' => [$gallery->id], 'pw_main_position' => 'top-left', 'pw_main_size' => 'xl',
                'pw_tile_size' => 'l', 'pw_gap' => 'wide']],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJsonPath('window_type', 'photowall')
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('main_position', 'top-left')
            ->assertJsonPath('tile_size', 'l');
    }

    public function test_control_dock_play_is_an_exclusive_switch(): void
    {
        $overlay = Overlay::create([
            'name' => 'C', 'type' => 'group_standings',
            'windows' => [['id' => 'w1', 'type' => 'groups', 'name' => 'W1'], ['id' => 'w2', 'type' => 'bracket', 'name' => 'W2']],
            'state' => ['active_window_ids' => ['w1'], 'next_match' => ''],
        ]);

        // playing another window switches: old off, new on
        $this->postJson("/overlay/{$overlay->token}/control", ['action' => 'play', 'window_id' => 'w2'])
            ->assertOk()->assertJson(['active_window_ids' => ['w2']]);
        $this->assertSame(['w2'], Overlay::activeIds($overlay->fresh()->state));

        // add=1 keeps the current set and adds
        $this->postJson("/overlay/{$overlay->token}/control", ['action' => 'play', 'window_id' => 'w1', 'add' => 1])
            ->assertOk();
        $ids = Overlay::activeIds($overlay->fresh()->state);
        sort($ids);
        $this->assertSame(['w1', 'w2'], $ids);
    }

    public function test_control_page_renders_windows(): void
    {
        $overlay = Overlay::create([
            'name' => 'C', 'type' => 'group_standings',
            'windows' => [['id' => 'w1', 'type' => 'groups', 'name' => 'Grupės A']],
        ]);

        $this->get("/overlay/{$overlay->token}/control")->assertOk()->assertSee('Grupės A');
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

    public function test_bracket_window_returns_segments_from_snapshot(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10424',
            'payload' => [
                'brackets_by_category' => [
                    '53642' => ['segments' => [
                        [
                            'key' => '900-main', 'label' => 'Pagrindinis', 'is_main' => true,
                            'rounds' => [
                                ['title' => 'Finalas', 'matches' => [
                                    ['team1' => 'A', 'team2' => 'C', 'sets1' => '6', 'sets2' => '2', 'winner' => 1, 'court' => 'Kortas 2', 'time' => '10:00'],
                                ]],
                            ],
                            'third' => ['team1' => 'B', 'team2' => 'D', 'sets1' => '', 'sets2' => '', 'winner' => 2],
                            'placements' => [],
                        ],
                        [
                            'key' => '900-place-7', 'label' => '5-8', 'is_main' => false,
                            'rounds' => [['title' => '', 'matches' => [
                                ['team1' => 'E', 'team2' => 'F', 'sets1' => '', 'sets2' => '', 'winner' => null],
                            ]]],
                            'third' => null, 'placements' => [],
                        ],
                    ]],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'B', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'bracket', 'name' => 'T', 'category_id' => 53642, 'segments' => []]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'bracket'])
            ->assertJsonPath('bracket.segments.0.label', 'Pagrindinis')
            ->assertJsonPath('bracket.segments.0.rounds.0.matches.0.court', 'Kortas 2')
            ->assertJsonPath('bracket.segments.0.third.team1', 'B')
            ->assertJsonPath('bracket.segments.1.label', '5-8');
    }

    public function test_bracket_window_filters_to_selected_segments(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10424',
            'payload' => [
                'brackets_by_category' => [
                    '53642' => ['segments' => [
                        ['key' => '900', 'label' => 'dėl 1 vietos', 'rounds' => [['title' => 'Finalas', 'matches' => []]], 'third' => null, 'placements' => []],
                        ['key' => '901', 'label' => 'dėl 3 vietos', 'rounds' => [['title' => 'Finalas', 'matches' => []]], 'third' => null, 'placements' => []],
                        ['key' => '902', 'label' => 'dėl 5 vietos', 'rounds' => [['title' => 'Finalas', 'matches' => []]], 'third' => null, 'placements' => []],
                    ]],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'B', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'bracket', 'name' => 'T', 'category_id' => 53642, 'segments' => ['901', '902']]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $res = $this->getJson("/overlay/{$overlay->token}/data")->assertOk();

        $this->assertSame(['dėl 3 vietos', 'dėl 5 vietos'], collect($res->json('bracket.segments'))->pluck('label')->all());
    }

    public function test_bracket_legacy_snapshot_shape_wrapped_as_single_segment(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10424',
            'payload' => [
                'brackets_by_category' => [
                    '53642' => [
                        'rounds' => [['title' => 'Finalas', 'matches' => [
                            ['team1' => 'A', 'team2' => 'C', 'sets1' => '6', 'sets2' => '2', 'winner' => 1],
                        ]]],
                        'third' => null, 'placements' => [],
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
            ->assertJsonPath('bracket.segments.0.label', 'Pagrindinis tinklelis')
            ->assertJsonPath('bracket.segments.0.rounds.0.matches.0.team1', 'A');
    }

    private function scheduleOverlay(string $variant, array $extra = []): Overlay
    {
        $mk = fn ($id, $date, $time, $court, $courtId, $cat, $status, $inProg) => [
            'id' => $id, 'date' => $date, 'time' => $time, 'duration' => 60,
            'court' => $court, 'court_id' => $courtId, 'category_id' => $cat, 'category' => 'Vyrai 40+',
            'status' => $status, 'in_progress' => $inProg, 'round' => 'R1', 'segment' => 'main',
            'score' => '6:2', 'team1' => ['A B'], 'team2' => ['C D'], 'winner' => 1,
        ];

        OverlaySnapshot::updateOrCreate(['tournament_external_id' => '10424'], ['payload' => [
            'matches' => [
                $mk(1, '2026-04-18', '11:00', 'Court 7', 7, 53642, 'completed', false),
                $mk(2, '2026-04-18', '12:00', 'Court 7', 7, 53642, 'pending', false),
                $mk(3, '2026-04-18', '11:00', 'Court 8', 8, 53636, 'pending', true),
                $mk(4, '2026-04-19', '11:00', 'Court 8', 8, 53642, 'pending', false),
            ],
        ]]);

        return Overlay::create([
            'name' => 'S', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [array_merge([
                'id' => 'w1', 'type' => 'schedule', 'name' => 'T', 'schedule_variant' => $variant,
                'date' => '2026-04-18', 'category_ids' => [], 'courts' => [], 'limit' => 6,
            ], $extra)],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);
    }

    public function test_schedule_by_court_groups_and_sorts(): void
    {
        $o = $this->scheduleOverlay('by_court');
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'schedule', 'schedule_variant' => 'by_court']);

        $this->assertSame(['Court 7', 'Court 8'], collect($res->json('schedule.groups'))->pluck('heading')->all());
        $this->assertSame(['11:00', '12:00'], collect($res->json('schedule.groups.0.matches'))->pluck('time')->all());
    }

    public function test_schedule_now_keeps_only_in_progress(): void
    {
        $o = $this->scheduleOverlay('now');
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk();
        $this->assertSame([3], collect($res->json('schedule.items'))->pluck('id')->all());
    }

    public function test_schedule_next_keeps_pending_sorted_by_time(): void
    {
        $o = $this->scheduleOverlay('next');
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk();
        $this->assertSame([3, 2], collect($res->json('schedule.items'))->pluck('id')->all());
    }

    public function test_schedule_filters_by_category_and_court(): void
    {
        $o = $this->scheduleOverlay('by_court', ['category_ids' => [53642], 'courts' => [7]]);
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk();
        $this->assertSame(['Court 7'], collect($res->json('schedule.groups'))->pluck('heading')->all());
        $this->assertSame([1, 2], collect($res->json('schedule.groups.0.matches'))->pluck('id')->all());
    }

    public function test_schedule_next_lists_in_progress_then_upcoming(): void
    {
        // id3 in_progress (11:00) before id2 pending (12:00); completed id1 excluded.
        $o = $this->scheduleOverlay('next');
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk();
        $this->assertSame([3, 2], collect($res->json('schedule.items'))->pluck('id')->all());
    }

    public function test_schedule_results_completed_with_score_newest_first(): void
    {
        $base = fn ($id, $time, $status, $finished, $score) => [
            'id' => $id, 'date' => '2026-04-18', 'time' => $time, 'duration' => 60,
            'court' => 'Court 7', 'court_id' => 7, 'category_id' => 53642, 'category' => 'Vyrai 40+',
            'status' => $status, 'in_progress' => false, 'round' => 'R1', 'segment' => 'main',
            'score' => $score, 'finished_at' => $finished, 'team1' => ['A B'], 'team2' => ['C D'], 'winner' => 1,
        ];

        $live = $base(5, '14:00', 'in_progress', null, '3:2');
        $live['in_progress'] = true; // has a score but still playing → excluded

        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'matches' => [
                $base(1, '11:00', 'completed', '2026-04-18T11:50:00', '6:2'),
                $base(2, '12:00', 'completed', '2026-04-18T13:05:00', '7:5'),
                $base(3, '13:00', 'pending', null, null),
                $base(4, '10:00', 'completed', null, null), // no score → excluded
                $base(6, '15:00', 'completed', null, '6:1'),  // no finish stamp → falls back to 15:00
                $live,
            ],
        ]]);

        $overlay = Overlay::create([
            'name' => 'R', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [[
                'id' => 'w1', 'type' => 'schedule', 'name' => 'T', 'schedule_variant' => 'results',
                'date' => '2026-04-18', 'category_ids' => [], 'courts' => [], 'limit' => 6,
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $res = $this->getJson("/overlay/{$overlay->token}/data")->assertOk()
            ->assertJson(['schedule_variant' => 'results']);
        // Newest first: id6 falls back to its 15:00 schedule, then id2 (13:05),
        // then id1 (11:50). Pending / scoreless / live are excluded.
        $this->assertSame([6, 2, 1], collect($res->json('schedule.items'))->pluck('id')->all());
    }
}
