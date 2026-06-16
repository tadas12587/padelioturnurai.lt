<?php

namespace Tests\Unit;

use App\Models\Overlay;
use PHPUnit\Framework\TestCase;

class OverlayThemeTest extends TestCase
{
    public function test_presets_include_new_themes_with_full_color_sets(): void
    {
        $presets = Overlay::themePresets();

        $this->assertGreaterThanOrEqual(11, count($presets));
        foreach (['gold_night', 'midnight', 'graphite', 'wine_gold', 'esports', 'orange', 'ice'] as $key) {
            $this->assertArrayHasKey($key, $presets);
            foreach (['bg', 'text', 'accent', 'muted'] as $slot) {
                $this->assertArrayHasKey($slot, $presets[$key]['colors']);
            }
        }
    }
}
