<?php

namespace Tests\Feature;

use App\Models\AppDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_page_is_public_in_both_locales(): void
    {
        $this->get('/privatumas')->assertOk()->assertSee('Privatumo politika')->assertSee('English summary');
        $this->get('/en/privatumas')->assertOk();
    }

    public function test_prune_removes_idle_devices_only(): void
    {
        $old = AppDevice::create(['expo_token' => 'ExponentPushToken[old]', 'role' => 'spectator']);
        $old->timestamps = false;
        $old->forceFill(['updated_at' => now()->subDays(61)])->save();
        AppDevice::create(['expo_token' => 'ExponentPushToken[new]', 'role' => 'spectator']);

        $this->artisan('schedule:test', ['--name' => 'push-prune'])->assertSuccessful();

        $this->assertSame(['ExponentPushToken[new]'], AppDevice::pluck('expo_token')->all());
    }
}
