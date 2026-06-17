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
}
