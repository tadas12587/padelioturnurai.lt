<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlaySponsorTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_sponsors_uses_selected_order_excludes_inactive_then_images(): void
    {
        $a = Sponsor::create(['name' => 'A', 'logo' => 'sponsors/a.png', 'url' => 'https://a.lt', 'category' => 'gold', 'is_active' => true]);
        $b = Sponsor::create(['name' => 'B', 'logo' => 'sponsors/b.png', 'category' => 'gold', 'is_active' => true]);
        $off = Sponsor::create(['name' => 'Off', 'logo' => 'sponsors/off.png', 'category' => 'gold', 'is_active' => false]);

        $window = [
            'type' => 'sponsors',
            'sponsor_ids' => [$b->id, $a->id, $off->id],
            'images' => ['overlay-sponsors/x.jpg'],
        ];

        $items = (new OverlayData)->resolveSponsors($window);

        $this->assertCount(3, $items);
        $this->assertSame('B', $items[0]['name']);
        $this->assertSame('A', $items[1]['name']);
        $this->assertSame('https://a.lt', $items[1]['url']);
        $this->assertNull($items[2]['name']);
        $this->assertStringContainsString('x.jpg', $items[2]['logo']);
    }
}
