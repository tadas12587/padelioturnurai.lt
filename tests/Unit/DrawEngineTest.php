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
}
