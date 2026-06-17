<?php

namespace Tests\Unit;

use App\Services\DrawEngine;
use Tests\TestCase;

class DrawEngineTest extends TestCase
{
    private DrawEngine $e;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e = new DrawEngine();
    }

    public function test_groups_layout_builds_lettered_slot_keys(): void
    {
        $layout = $this->e->layout(['format' => 'groups', 'group_count' => 2, 'group_size' => 3]);

        $this->assertSame('groups', $layout['format']);
        $this->assertSame('A', $layout['groups'][0]['label']);
        $this->assertSame(['A1', 'A2', 'A3'], $layout['groups'][0]['slots']);
        $this->assertSame(['B1', 'B2', 'B3'], $layout['groups'][1]['slots']);
    }

    public function test_bracket_layout_pairs_consecutive_physical_slots(): void
    {
        $layout = $this->e->layout(['format' => 'bracket', 'bracket_size' => 4]);

        $this->assertSame('bracket', $layout['format']);
        $this->assertSame([['1', '2'], ['3', '4']], $layout['pairs']);
    }

    public function test_init_creates_empty_slots_and_idle_status(): void
    {
        $config = ['format' => 'groups', 'group_count' => 2, 'group_size' => 2];
        $teams = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']];

        $state = $this->e->init($config, $teams);

        $this->assertSame(['A1' => null, 'A2' => null, 'B1' => null, 'B2' => null], $state['slots']);
        $this->assertCount(2, $state['teams']);
        $this->assertNull($state['current']);
        $this->assertSame([], $state['history']);
        $this->assertSame(1, $state['active_pot']);
        $this->assertSame('idle', $state['status']);
    }

    public function test_bracket_seed_order_recursive_doubling(): void
    {
        $this->assertSame([1, 2], $this->e->bracketSeedOrder(2));
        $this->assertSame([1, 4, 2, 3], $this->e->bracketSeedOrder(4));
        $this->assertSame([1, 8, 4, 5, 2, 7, 3, 6], $this->e->bracketSeedOrder(8));
    }

    public function test_bracket_pot_of_seed_bands(): void
    {
        $this->assertSame(1, $this->e->bracketPotOfSeed(1));
        $this->assertSame(1, $this->e->bracketPotOfSeed(2));
        $this->assertSame(2, $this->e->bracketPotOfSeed(3));
        $this->assertSame(2, $this->e->bracketPotOfSeed(4));
        $this->assertSame(3, $this->e->bracketPotOfSeed(5));
        $this->assertSame(3, $this->e->bracketPotOfSeed(8));
        $this->assertSame(4, $this->e->bracketPotOfSeed(9));
    }

    public function test_bracket_slot_for_seed_maps_to_physical_position(): void
    {
        // n=8 order [1,8,4,5,2,7,3,6]: seed 1 → slot "1", seed 2 → slot "5".
        $this->assertSame('1', $this->e->bracketSlotForSeed(8, 1));
        $this->assertSame('5', $this->e->bracketSlotForSeed(8, 2));
        $this->assertSame('7', $this->e->bracketSlotForSeed(8, 3));
    }

    public function test_groups_draw_distributes_pot_one_per_group_then_advances(): void
    {
        $config = ['format' => 'groups', 'group_count' => 2, 'group_size' => 2, 'use_pots' => true];
        $teams = [
            ['id' => 1, 'name' => 'P1a', 'pot' => 1], ['id' => 2, 'name' => 'P1b', 'pot' => 1],
            ['id' => 3, 'name' => 'P2a', 'pot' => 2], ['id' => 4, 'name' => 'P2b', 'pot' => 2],
        ];
        $state = $this->e->init($config, $teams);
        $rng = fn (int $c) => 0; // always first candidate / first group

        $state = $this->e->drawNext($config, $state, $rng);
        $this->assertSame(1, $state['slots']['A1']);          // pot1 team → group A pos1
        $this->assertSame(['team_id' => 1, 'slot' => 'A1'], $state['current']);

        $state = $this->e->drawNext($config, $state, $rng);
        $this->assertSame(2, $state['slots']['B1']);          // pot1 second team → group B (A already has pot1)

        $state = $this->e->drawNext($config, $state, $rng);
        $this->assertSame(2, $state['active_pot']);           // pot1 exhausted → pot2
        $this->assertSame(3, $state['slots']['A2']);          // pot2 → group A next free pos
    }

    public function test_groups_draw_without_pot_data_does_not_throw(): void
    {
        // Real Tournated participants arrive with no pot/seed; use_pots on must
        // still draw (treats all teams as one band) instead of throwing.
        $config = ['format' => 'groups', 'group_count' => 2, 'group_size' => 1, 'use_pots' => true];
        $teams = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']];
        $state = $this->e->init($config, $teams);

        $state = $this->e->drawNext($config, $state, fn (int $c) => 0);
        $state = $this->e->drawNext($config, $state, fn (int $c) => 0);

        $placed = array_filter($state['slots'], fn ($t) => $t !== null);
        $this->assertCount(2, $placed);
        $this->assertSame('done', $state['status']);
    }

    public function test_draw_marks_done_when_pool_empty(): void
    {
        $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 1, 'use_pots' => true];
        $state = $this->e->init($config, [['id' => 1, 'name' => 'X', 'pot' => 1]]);
        $state = $this->e->drawNext($config, $state, fn (int $c) => 0);

        $this->assertSame('done', $state['status']);
        $this->assertSame(1, $state['slots']['A1']);
    }

    public function test_draw_throws_when_already_done(): void
    {
        $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 1];
        $state = $this->e->init($config, [['id' => 1, 'name' => 'X', 'pot' => 1]]);
        $state = $this->e->drawNext($config, $state, fn (int $c) => 0);

        $this->expectException(\RuntimeException::class);
        $this->e->drawNext($config, $state, fn (int $c) => 0);
    }

    public function test_bracket_draw_places_seeds_at_band_anchor_slots(): void
    {
        $config = ['format' => 'bracket', 'bracket_size' => 4, 'use_pots' => true];
        // n=4 order [1,4,2,3]: seed1 → slot "1" (idx0), seed2 → slot "3" (idx2).
        $teams = [
            ['id' => 10, 'name' => 'S1', 'seed' => 1],
            ['id' => 20, 'name' => 'S2', 'seed' => 2],
            ['id' => 30, 'name' => 'U1'],
            ['id' => 40, 'name' => 'U2'],
        ];
        $state = $this->e->init($config, $teams);
        $rng = fn (int $c) => 0;

        $state = $this->e->drawNext($config, $state, $rng); // pot1 seed → its anchor slot
        $this->assertSame(10, $state['slots']['1']);         // seed1 anchor = physical slot 1

        $state = $this->e->drawNext($config, $state, $rng);
        $this->assertSame(20, $state['slots']['3']);         // seed2 anchor = physical slot 3 (idx2)

        $state = $this->e->drawNext($config, $state, $rng);  // unseeded → remaining free slot
        $this->assertContains($state['current']['slot'], ['2', '4']);
    }

    public function test_manual_place_sets_slot_and_records_history(): void
    {
        $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 2];
        $state = $this->e->init($config, [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);

        $state = $this->e->place($config, $state, 2, 'A2');

        $this->assertSame(2, $state['slots']['A2']);
        $this->assertSame(['team_id' => 2, 'slot' => 'A2'], end($state['history']));
    }

    public function test_place_rejects_occupied_slot(): void
    {
        $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 2];
        $state = $this->e->init($config, [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
        $state = $this->e->place($config, $state, 1, 'A1');

        $this->expectException(\RuntimeException::class);
        $this->e->place($config, $state, 2, 'A1');
    }

    public function test_undo_frees_last_slot_and_returns_team_to_pool(): void
    {
        $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 2, 'use_pots' => false];
        $state = $this->e->init($config, [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
        $state = $this->e->drawNext($config, $state, fn (int $c) => 0);
        $slot = $state['current']['slot'];

        $state = $this->e->undo($config, $state);

        $this->assertNull($state['slots'][$slot]);
        $this->assertSame([], $state['history']);
        $this->assertSame('idle', $state['status']);
    }

    public function test_place_bye_fills_slot_without_consuming_pool(): void
    {
        $config = ['format' => 'bracket', 'bracket_size' => 4];
        $state = $this->e->init($config, [['id' => 1, 'name' => 'A']]);

        $state = $this->e->place($config, $state, 'BYE', '2');

        $this->assertSame('BYE', $state['slots']['2']);
        // The only real team is still unplaced, so the draw is not done.
        $this->assertSame('idle', $state['status']);
        // A BYE can be placed in more than one slot.
        $state = $this->e->place($config, $state, 'BYE', '3');
        $this->assertSame('BYE', $state['slots']['3']);
    }

    public function test_reset_clears_all_slots_but_keeps_teams(): void
    {
        $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 2];
        $state = $this->e->init($config, [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
        $state = $this->e->place($config, $state, 1, 'A1');

        $state = $this->e->reset($config, $state);

        $this->assertSame(['A1' => null, 'A2' => null], $state['slots']);
        $this->assertCount(2, $state['teams']);
        $this->assertSame([], $state['history']);
    }
}
