<?php

namespace Tests\Feature;

use App\Models\Overlay;
use App\Models\OverlaySnapshot;
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
}
