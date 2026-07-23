<?php

namespace Tests\Feature;

use App\Models\OverlaySnapshot;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlayFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_map_builds_urls_from_scraped_nation(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'people' => [
                ['id' => 1, 'name' => 'Jonas Petraitis', 'nation' => 'LT'],
                ['id' => 2, 'name' => 'Aleksandr Radiuš', 'nation' => 'LV'],
                ['id' => 3, 'name' => 'Be Salies', 'nation' => null],
            ],
        ]]);

        $map = app(OverlayData::class)->flagMap('10424');

        $this->assertSame('https://flagcdn.com/32x24/lt.png', $map['jonas petraitis']);
        // personKey strips the diacritic (š → s), matching the JS renderer.
        $this->assertSame('https://flagcdn.com/32x24/lv.png', $map['aleksandr radius']);
        $this->assertArrayNotHasKey('be salies', $map); // no nation → no flag
    }

    public function test_resolve_score_attaches_flag_per_player_on_full_names(): void
    {
        $flagMap = ['jevgenij grigorenko' => 'https://flagcdn.com/32x24/lt.png'];
        $state = [
            'teams' => [['Jevgenij Grigorenko', 'Nezinomas Zaidejas'], ['A B', 'C D']],
            'sets' => [], 'games' => [0, 0], 'points' => [0, 0], 'status' => 'playing',
        ];

        $sc = app(OverlayData::class)->resolveScore([], $state, [], [], $flagMap);

        // Name is abbreviated for display, flag resolved from the full name.
        $this->assertSame('J. Grigorenko', $sc['teams'][0]['players'][0]['name']);
        $this->assertSame('https://flagcdn.com/32x24/lt.png', $sc['teams'][0]['players'][0]['flag']);
        $this->assertNull($sc['teams'][0]['players'][1]['flag']);
    }
}
