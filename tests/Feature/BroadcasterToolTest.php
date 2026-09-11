<?php

namespace Tests\Feature;

use App\Filament\Pages\BroadcasterToolPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BroadcasterToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_reflect_storage_presence(): void
    {
        Storage::fake('public');

        $before = collect((new BroadcasterToolPage())->downloads())->firstWhere('os', 'win');
        $this->assertFalse($before['exists']);
        $this->assertNull($before['url']);

        Storage::disk('public')->put('broadcaster/overlay-push-win.exe', 'binary');

        $after = collect((new BroadcasterToolPage())->downloads())->firstWhere('os', 'win');
        $this->assertTrue($after['exists']);
        $this->assertNotNull($after['url']);
    }

    public function test_downloads_lists_all_three_targets(): void
    {
        Storage::fake('public');

        $oses = array_column((new BroadcasterToolPage())->downloads(), 'os');
        $this->assertSame(['win', 'mac-arm', 'mac-intel'], $oses);
    }
}
