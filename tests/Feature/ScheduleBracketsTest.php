<?php

namespace Tests\Feature;

use App\Models\ScheduleSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleBracketsTest extends TestCase
{
    use RefreshDatabase;

    private function match(int $id, string $round, array $sides, array $extra = []): array
    {
        $participants = [];
        foreach ($sides as $side => $names) {
            foreach ($names as $n) {
                [$first, $last] = explode(' ', $n);
                $participants[] = ['side' => $side, 'name' => $first, 'surname' => $last];
            }
        }

        return $extra + [
            'match_id' => $id, 'date' => '2026-07-25', 'time' => '10:00', 'court' => ['court_id' => 1, 'name' => 'Court 1'],
            'participants' => $participants, 'sets' => [], 'winner_side' => null, 'is_bye' => false,
            'round' => $round, 'bracket_type' => 'main',
        ];
    }

    private function seedDraw(): void
    {
        ScheduleSnapshot::create(['tournament_external_id' => '900', 'payload' => [
            'tournament' => ['name' => 'Testas', 'tz' => 'Europe/Vilnius'],
            'matches' => [
                $this->match(1, 'Semi-Final', [1 => ['Ana A', 'Bea B'], 2 => ['Cid C', 'Dan D']], ['sets' => [['side1' => 6, 'side2' => 2]], 'winner_side' => 1]),
                // bye: one side only, no court -> hidden from the schedule, kept as a bracket slot
                $this->match(2, 'Semi-Final', [1 => ['Eva E', 'Fay F']], ['time' => null, 'court' => null, 'is_bye' => true]),
                // final not drawn yet
                $this->match(3, 'Final', [], ['time' => null, 'court' => null]),
            ],
            'draws' => [[
                'draw_id' => 77, 'category_id' => 5, 'title' => 'Vyrai A', 'size' => 4, 'type' => 'play-each-place',
                'entries' => [['position' => 1, 'player' => ['player_id' => 123, 'name' => 'Ana', 'surname' => 'A']]],
                'match_ids' => [1, 2, 3],
            ]],
            'groups' => [],
            'standings' => [['category_id' => 5, 'entries' => [['position' => 1, 'points' => 100, 'player' => ['player_id' => 123]]]]],
            'synced_at' => now()->toIso8601String(),
        ]]);
    }

    public function test_data_endpoint_returns_draws_with_slots_and_no_player_ids_or_points(): void
    {
        $this->seedDraw();

        $json = $this->getJson('/grafikas/900/data')->assertOk()->json();

        $this->assertArrayNotHasKey('standings', $json);
        $this->assertCount(1, $json['draws']);
        $draw = $json['draws'][0];
        $this->assertSame('Vyrai A', $draw['title']);
        $this->assertSame([1, 2, 3], $draw['match_ids']);
        $this->assertArrayNotHasKey('entries', $draw);
        $slots = collect($draw['slots'])->keyBy('match_id');
        $this->assertSame([2, 3], $slots->keys()->all());       // match 1 comes from `matches`
        $this->assertTrue($slots[2]['bye']);
        $this->assertSame(['Eva E', 'Fay F'], $slots[2]['side1']);
        $this->assertFalse($slots[3]['bye']);                   // just not drawn yet
        $this->assertSame('Final', $slots[3]['round']);
        $this->assertStringNotContainsString('player_id', json_encode($json));
    }

    public function test_page_labels_the_tab_by_what_the_tournament_has(): void
    {
        $this->seedDraw();
        $this->get('/grafikas/900')->assertOk()->assertSee('data-tab="lenteles"', false)->assertDontSee('data-tab="grupes"', false)->assertDontSee('player_id');

        ScheduleSnapshot::create(['tournament_external_id' => '901', 'payload' => [
            'tournament' => ['name' => 'Lyga'],
            'matches' => [$this->match(10, 'R1', [1 => ['Ana A'], 2 => ['Cid C']], ['team1' => ['title' => 'Klubas 1'], 'team2' => ['title' => 'Klubas 2']])],
            'groups' => [['group_id' => 1, 'name' => 'Bendra', 'match_ids' => [10], 'entries' => [['player' => ['player_id' => 9]]]]],
        ]]);
        $this->get('/grafikas/901')->assertOk()->assertSee('data-tab="grupes"', false)->assertDontSee('data-tab="lenteles"', false)->assertDontSee('player_id');
    }
}
