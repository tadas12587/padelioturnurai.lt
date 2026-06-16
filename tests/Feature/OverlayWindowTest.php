<?php

namespace Tests\Feature;

use App\Models\OverlaySnapshot;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlayWindowTest extends TestCase
{
    use RefreshDatabase;

    private function seedSnapshot(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10229',
            'payload' => [
                'title' => 'T',
                'categories' => [],
                'category_stages' => ['47817' => ['has_groups' => true, 'has_bracket' => false]],
                'groups_by_category' => [
                    '47817' => [
                        ['id' => 1, 'name' => 'A', 'entries' => [], 'matches' => []],
                        ['id' => 2, 'name' => 'B', 'entries' => [], 'matches' => []],
                    ],
                ],
            ],
        ]);
    }

    public function test_resolve_window_all_subgroups(): void
    {
        $this->seedSnapshot();
        $window = ['id' => 'w1', 'type' => 'groups', 'subgroups' => [['category_id' => 47817, 'group_id' => null]]];

        $res = (new OverlayData)->resolveWindow('10229', $window);

        $this->assertSame(2, $res['subgroup_count']);
    }

    public function test_resolve_window_single_subgroup(): void
    {
        $this->seedSnapshot();
        $window = ['id' => 'w1', 'type' => 'groups', 'subgroups' => [['category_id' => 47817, 'group_id' => 2]]];

        $res = (new OverlayData)->resolveWindow('10229', $window);

        $this->assertSame(1, $res['subgroup_count']);
        $this->assertSame('B', $res['groups'][0]['name']);
    }
}
