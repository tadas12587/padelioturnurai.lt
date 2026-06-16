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
        $this->assertSame('#C9A84C', $overlay->config['accent_color']);
        $this->assertFalse($overlay->state['visible']);
    }

    public function test_data_endpoint_404_for_unknown_token(): void
    {
        $this->getJson('/overlay/nope1234/data')->assertNotFound();
    }

    public function test_data_endpoint_hidden_when_not_visible(): void
    {
        $overlay = Overlay::create(['name' => 'G', 'type' => 'group_standings']);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => false]);
    }

    public function test_data_endpoint_marks_stale_when_no_snapshot(): void
    {
        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'tournament_external_id' => '10229',
            'state' => ['active_category_id' => 47817, 'visible' => true],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'groups' => [], 'stale' => true]);
    }

    public function test_data_endpoint_returns_groups_from_snapshot(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10229',
            'payload' => [
                'title' => 'T',
                'categories' => [],
                'groups_by_category' => [
                    '47817' => [[
                        'id' => 5, 'name' => 'A', 'segment' => 'MD',
                        'entries' => [], 'matches' => [],
                    ]],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'tournament_external_id' => '10229',
            'state' => ['active_category_id' => 47817, 'active_group_id' => null, 'visible' => true, 'next_match' => 'Next: A vs B'],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson([
                'visible' => true,
                'type'    => 'group_standings',
                'next_match' => 'Next: A vs B',
                'subgroup_count' => 1,
            ]);
    }
}
