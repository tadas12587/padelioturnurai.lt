<?php

namespace Tests\Feature;

use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Models\ScheduleSnapshot;
use App\Services\Push\PushEvents;
use App\Services\Push\WebPush;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Vilnius';

    private function now(string $hhmm = '11:03'): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-11-14 {$hhmm}", self::TZ);
    }

    private function match(int $id, string $time, array $players, string $court = 'Court 1', bool $played = false): array
    {
        $participants = [];
        foreach ($players as $i => $full) {
            [$name, $surname] = explode(' ', $full, 2);
            $participants[] = ['side' => $i < 2 ? 1 : 2, 'name' => $name, 'surname' => $surname];
        }

        return [
            'match_id' => $id, 'date' => '2026-11-14', 'time' => $time, 'court' => ['court_id' => 1, 'name' => $court],
            'participants' => $participants, 'team1' => ['title' => 'BTC Klubas'], 'team2' => ['title' => 'Pasitaškom'],
            'division' => 'Vyrai B- 1', 'sets' => $played ? [['side1' => 6, 'side2' => 3]] : [], 'winner_side' => $played ? 1 : null,
            'result_seen_at' => $played ? $this->now('11:02')->toIso8601String() : null, 'result_batch' => false,
        ];
    }

    private function subscribe(string $player): PushSubscription
    {
        return PushSubscription::create([
            'tournament_external_id' => '900', 'player' => $player, 'player_key' => PushSubscription::key($player),
            'endpoint_hash' => hash('sha256', $player), 'endpoint' => 'https://push.example.test/'.md5($player),
            'p256dh' => 'x', 'auth' => 'y',
        ]);
    }

    private const A = ['Jonas Jonaitis', 'Petras Petraitis', 'Ona Onaitė', 'Rūta Rūtaitė'];

    private const B = ['Robertas Šimkus', 'Agnius Čiapas', 'Marius Povilavičius', 'Ignas Riauba'];

    public function test_result_on_a_court_tells_the_next_match_players_to_go(): void
    {
        $sub = $this->subscribe('Robertas Simkus'); // typed without diacritics
        $other = $this->subscribe('Jonas Jonaitis');
        $before = [$this->match(1, '10:00', self::A), $this->match(2, '10:50', self::B)];
        $after = [$this->match(1, '10:00', self::A, played: true), $this->match(2, '10:50', self::B)];

        PushEvents::detect('900', $before, $after, true, self::TZ, $this->now());

        $msg = PushMessage::where('push_subscription_id', $sub->id)->sole();
        $this->assertSame('turn', $msg->kind);
        $this->assertSame('Tavo eilė! Eik į Kortą 1', $msg->title);
        $this->assertStringContainsString('BTC Klubas – Pasitaškom', $msg->body);
        $this->assertSame('/grafikas/900?z=Robertas%20Simkus', $msg->url);
        $this->assertSame(0, PushMessage::where('push_subscription_id', $other->id)->count()); // just finished, nothing to do

        // the next sync must not send it again
        PushEvents::detect('900', $before, $after, true, self::TZ, $this->now('11:04'));
        $this->assertSame(1, PushMessage::count());
    }

    public function test_results_entered_in_a_batch_do_not_send_turn_messages(): void
    {
        $this->subscribe('Robertas Šimkus');
        $done = $this->match(1, '10:00', self::A, played: true);
        $done['result_batch'] = true;

        PushEvents::detect('900', [$this->match(1, '10:00', self::A), $this->match(2, '10:50', self::B)], [$done, $this->match(2, '10:50', self::B)], true, self::TZ, $this->now());

        $this->assertSame(0, PushMessage::count());
    }

    public function test_first_match_of_the_day_is_announced_ten_minutes_before(): void
    {
        $this->subscribe('Ona Onaitė');
        $m = [$this->match(1, '10:00', self::A)];

        PushEvents::detect('900', $m, $m, true, self::TZ, $this->now('09:45'));
        $this->assertSame(0, PushMessage::count());

        PushEvents::detect('900', $m, $m, true, self::TZ, $this->now('09:51'));
        $this->assertSame('Tavo eilė! Eik į Kortą 1', PushMessage::sole()->title);
    }

    public function test_court_time_pairs_and_removal_changes_are_announced(): void
    {
        $this->subscribe('Robertas Šimkus');
        $this->subscribe('Jonas Jonaitis');
        $before = [$this->match(1, '12:00', self::A), $this->match(2, '13:00', self::B)];

        $moved = $this->match(2, '13:30', self::B, court: 'Court 3');
        PushEvents::detect('900', $before, [$before[0], $moved], true, self::TZ, $this->now('09:00'));
        $kinds = PushMessage::pluck('kind', 'title')->all();
        $this->assertSame('court', $kinds['Pasikeitė kortas: dabar Kortas 3']);
        $this->assertSame('time', $kinds['Pasikeitė mačo laikas: ~13:30']);
        $this->assertFalse(PushMessage::where('kind', 'gone')->exists()); // nothing removed yet

        $swapped = $this->match(2, '13:30', ['Robertas Šimkus', 'Agnius Čiapas', 'Tomas Tomaitis', 'Ignas Riauba'], court: 'Court 3');
        PushEvents::detect('900', [$before[0], $moved], [$before[0], $swapped], true, self::TZ, $this->now('09:01'));
        $this->assertTrue(PushMessage::where('kind', 'opp')->exists());

        PushEvents::detect('900', [$before[0], $swapped], [$swapped], true, self::TZ, $this->now('09:02'));
        $this->assertSame('Mačas išimtas iš grafiko', PushMessage::where('kind', 'gone')->sole()->title);
    }

    public function test_subscribe_and_dispatch_send_and_drop_expired_subscriptions(): void
    {
        $keys = WebPush::generateVapidKeys();
        $pem = tempnam(sys_get_temp_dir(), 'vapid');
        file_put_contents($pem, $keys['private']);
        config(['services.push' => ['vapid_public' => $keys['public'], 'vapid_private_path' => $pem, 'subject' => 'https://padelioturnyrai.lt', 'dispatch_token' => 'secret']]);
        ScheduleSnapshot::create(['tournament_external_id' => '900', 'payload' => ['matches' => []]]);

        // a real browser key pair for the subscription
        $ua = WebPush::newEcKey();
        $sub = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => [
            'p256dh' => WebPush::b64urlEncode(WebPush::rawPublicKey($ua)), 'auth' => WebPush::b64urlEncode(random_bytes(16)),
        ]];
        $this->postJson('/grafikas/900/push/subscribe', ['player' => 'Robertas Šimkus', 'subscription' => $sub])->assertOk();
        $this->postJson('/grafikas/900/push/subscribe', ['player' => 'Agnius Čiapas', 'subscription' => $sub])->assertOk(); // same phone, other player
        $s = PushSubscription::sole();
        $this->assertSame('agnius ciapas', $s->player_key);

        PushMessage::create(['push_subscription_id' => $s->id, 'dedupe_key' => 'turn:1', 'kind' => 'turn', 'title' => 'Tavo eilė!', 'body' => 'x', 'url' => '/']);
        $old = PushMessage::create(['push_subscription_id' => $s->id, 'dedupe_key' => 'turn:0', 'kind' => 'turn', 'title' => 'senas', 'body' => 'x', 'url' => '/']);
        $old->forceFill(['created_at' => now()->subMinutes(20)])->save();

        $this->postJson('/push/dispatch')->assertForbidden();

        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $this->postJson('/push/dispatch', [], ['X-Push-Token' => 'secret'])->assertOk()->assertJson(['sent' => 1]);
        $this->assertSame('sent', PushMessage::where('dedupe_key', 'turn:1')->value('status'));
        $this->assertSame('expired', PushMessage::where('dedupe_key', 'turn:0')->value('status'));

        PushMessage::create(['push_subscription_id' => $s->id, 'dedupe_key' => 'turn:2', 'kind' => 'turn', 'title' => 'x', 'body' => 'x', 'url' => '/']);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 410)]);
        $this->postJson('/push/dispatch', [], ['X-Push-Token' => 'secret'])->assertOk();
        $this->assertSame(0, PushSubscription::count());
        @unlink($pem);
    }

    public function test_subscribe_only_accepts_real_push_services_and_valid_keys(): void
    {
        ScheduleSnapshot::create(['tournament_external_id' => '900', 'payload' => ['matches' => []]]);
        $keys = ['p256dh' => WebPush::b64urlEncode(WebPush::rawPublicKey(WebPush::newEcKey())), 'auth' => WebPush::b64urlEncode(random_bytes(16))];
        $post = fn ($endpoint, $k = null) => $this->postJson('/grafikas/900/push/subscribe', ['player' => 'Ona Onaitė', 'subscription' => ['endpoint' => $endpoint, 'keys' => $k ?? $keys]]);

        $post('https://169.254.169.254/latest/meta-data')->assertStatus(422);
        $post('https://evil.example.com/fcm.googleapis.com')->assertStatus(422);
        $post('https://fcm.googleapis.com:8443/x')->assertStatus(422);
        $post('https://fcm.googleapis.com/fcm/send/abc', ['p256dh' => 'AAAA', 'auth' => 'BBBB'])->assertStatus(422);
        $this->assertSame(0, PushSubscription::count());

        $post('https://web.push.apple.com/QF1')->assertOk();
        $post('https://wns2-par02p.notify.windows.com/w/?token=x')->assertOk();
        $this->assertSame(2, PushSubscription::count());
    }

    public function test_service_worker_manifest_and_icon_are_served(): void
    {
        ScheduleSnapshot::create(['tournament_external_id' => '900', 'payload' => ['tournament' => ['name' => 'Klubų lyga']]]);

        $this->get('/sw.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->get('/grafikas/900/manifest.webmanifest')->assertOk()->assertJson(['name' => 'Klubų lyga', 'start_url' => '/grafikas/900']);
        $this->get('/grafikas-icon/192.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    }
}
