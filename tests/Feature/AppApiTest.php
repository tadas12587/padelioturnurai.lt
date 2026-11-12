<?php

namespace Tests\Feature;

use App\Models\AppDevice;
use App\Models\PushMessage;
use App\Models\ScheduleSnapshot;
use App\Models\Tournament;
use App\Services\Push\PushEvents;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ExponentPushToken[abcdefghijklmnop123]';

    private function snapshot(string $id = '900'): void
    {
        ScheduleSnapshot::create(['tournament_external_id' => $id, 'payload' => ['tournament' => ['name' => 'Klubų lyga'], 'matches' => []]]);
    }

    private function match(int $id, string $time, array $players, bool $played = false): array
    {
        $participants = [];
        foreach ($players as $i => $full) {
            [$name, $surname] = explode(' ', $full, 2);
            $participants[] = ['side' => $i < 2 ? 1 : 2, 'name' => $name, 'surname' => $surname];
        }

        return [
            'match_id' => $id, 'date' => '2026-11-26', 'time' => $time, 'court' => ['court_id' => 1, 'name' => 'Court 2'],
            'participants' => $participants, 'team1' => ['title' => 'BTC Klubas'], 'team2' => ['title' => 'Pasitaškom'],
            'sets' => $played ? [['side1' => 4, 'side2' => 6], ['side1' => 3, 'side2' => 6]] : [], 'winner_side' => $played ? 2 : null,
            'result_seen_at' => $played ? '2026-11-26T09:02:00+00:00' : null, 'result_batch' => false,
        ];
    }

    public function test_tournament_list_shows_our_public_tournaments_running_first(): void
    {
        $this->snapshot('900');
        $this->snapshot('901');
        $this->snapshot('902');
        Tournament::create(['slug' => 'senas', 'tournated_id' => '900', 'status' => 'past', 'date_start' => '2026-09-12', 'location' => 'Klaipėda']);
        Tournament::create(['slug' => 'naujas', 'tournated_id' => '901', 'status' => 'upcoming', 'date_start' => now()->addDays(10)->toDateString(), 'location' => 'Klaipėda']);
        Tournament::create(['slug' => 'slaptas', 'tournated_id' => '902', 'show_schedule' => false, 'status' => 'upcoming', 'date_start' => now()->toDateString(), 'location' => 'X']);
        Tournament::create(['slug' => 'be-tournated', 'status' => 'upcoming', 'date_start' => now()->toDateString(), 'location' => 'X']);

        $list = $this->getJson('/api/app/v1/tournaments')->assertOk()->json('tournaments');

        $this->assertSame(['901', '900'], array_column($list, 'id'));
        $this->assertSame('upcoming', $list[0]['phase']);
        $this->assertSame('Klubų lyga', $list[1]['name']);
        $this->getJson('/api/app/v1/tournaments/900')->assertOk()->assertJsonStructure(['matches', 'court_delays']);
    }

    public function test_register_device_validates_and_upserts(): void
    {
        $this->snapshot();
        $this->postJson('/api/app/v1/devices', ['token' => 'nope', 'tournament' => '900', 'role' => 'spectator'])->assertStatus(422);
        $this->postJson('/api/app/v1/devices', ['token' => self::TOKEN, 'tournament' => '900', 'role' => 'player'])->assertStatus(422); // player needs "me"

        $this->postJson('/api/app/v1/devices', ['token' => self::TOKEN, 'platform' => 'android', 'tournament' => '900', 'role' => 'player', 'me' => 'Robertas Šimkus'])->assertOk();
        $this->postJson('/api/app/v1/devices', ['token' => self::TOKEN, 'tournament' => '900', 'role' => 'spectator', 'follows' => ['Ona Onaitė', 'Robertas Šimkus']])->assertOk();

        $d = AppDevice::sole();
        $this->assertSame('spectator', $d->role);
        $this->assertNull($d->me);
        $this->assertSame(['ona onaite', 'robertas simkus'], $d->follow_keys);

        $this->postJson('/api/app/v1/devices/delete', ['token' => self::TOKEN])->assertOk();
        $this->assertSame(0, AppDevice::count());
    }

    public function test_players_get_their_turn_and_spectators_get_turn_and_results_of_followed(): void
    {
        $player = AppDevice::create(['expo_token' => 'ExponentPushToken[player0000001]', 'tournament_external_id' => '900', 'role' => 'player', 'me' => 'Robertas Šimkus', 'me_key' => 'robertas simkus']);
        $fan = AppDevice::create(['expo_token' => 'ExponentPushToken[fan0000000001]', 'tournament_external_id' => '900', 'role' => 'spectator',
            'follows' => ['Ona Onaitė', 'Robertas Šimkus'], 'follow_keys' => ['ona onaite', 'robertas simkus']]);
        $a = ['Jonas Jonaitis', 'Petras Petraitis', 'Ona Onaitė', 'Rūta Rūtaitė'];
        $b = ['Robertas Šimkus', 'Agnius Čiapas', 'Marius Povilavičius', 'Ignas Riauba'];

        PushEvents::detect('900', [$this->match(1, '10:00', $a), $this->match(2, '10:50', $b)],
            [$this->match(1, '10:00', $a, true), $this->match(2, '10:50', $b)], true, 'Europe/Vilnius', CarbonImmutable::parse('2026-11-26 11:03', 'Europe/Vilnius'));

        $this->assertSame(['Tavo eilė! Eik į Kortą 2'], PushMessage::where('app_device_id', $player->id)->pluck('title')->all());
        $fanTitles = PushMessage::where('app_device_id', $fan->id)->pluck('title', 'kind')->all();
        $this->assertSame('Ona Onaitė laimėjo', $fanTitles['result']);
        $this->assertSame('Robertas Šimkus · eina į Kortą 2', $fanTitles['turn']);
        $this->assertStringContainsString('4-6 3-6', PushMessage::where('app_device_id', $fan->id)->where('kind', 'result')->value('body'));
    }

    public function test_dispatch_sends_app_messages_through_expo_and_drops_unregistered_phones(): void
    {
        config(['services.push.dispatch_token' => 'secret']);
        $ok = AppDevice::create(['expo_token' => 'ExponentPushToken[ok00000000001]', 'tournament_external_id' => '900', 'role' => 'spectator']);
        $gone = AppDevice::create(['expo_token' => 'ExponentPushToken[gone000000001]', 'tournament_external_id' => '900', 'role' => 'spectator']);
        foreach ([$ok, $gone] as $d) {
            PushMessage::create(['app_device_id' => $d->id, 'dedupe_key' => 'turn:1', 'kind' => 'turn', 'title' => 'T', 'body' => 'B', 'url' => '1']);
        }
        Http::fake(['exp.host/*' => Http::response(['data' => [
            ['status' => 'ok', 'id' => 'x'],
            ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);

        $this->postJson('/push/dispatch', [], ['X-Push-Token' => 'secret'])->assertOk()->assertJson(['sent' => 1]);

        $this->assertSame('sent', PushMessage::where('app_device_id', $ok->id)->value('status'));
        $this->assertFalse(AppDevice::whereKey($gone->id)->exists());
        Http::assertSent(fn ($r) => $r->url() === 'https://exp.host/--/api/v2/push/send' && $r[0]['to'] === 'ExponentPushToken[ok00000000001]' && $r[0]['sound'] === 'default');
    }

    public function test_turn_alert_for_the_player_is_loud_and_others_are_normal(): void
    {
        $loud = \App\Services\Push\ExpoPush::payload(['to' => 'x', 'title' => 'Tavo eilė!', 'body' => 'b', 'urgent' => true]);
        $this->assertSame('eile.wav', $loud['sound']);
        $this->assertSame('eile', $loud['channelId']);
        $this->assertSame('time-sensitive', $loud['interruptionLevel']);
        $this->assertArrayNotHasKey('urgent', $loud);

        $normal = \App\Services\Push\ExpoPush::payload(['to' => 'x', 'title' => 'Kortas pasikeitė', 'body' => 'b']);
        $this->assertSame('default', $normal['channelId']);
        $this->assertArrayNotHasKey('interruptionLevel', $normal);
    }
}
