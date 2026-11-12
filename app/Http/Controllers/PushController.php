<?php

namespace App\Http\Controllers;

use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Models\ScheduleSnapshot;
use App\Services\Push\ExpoPush;
use App\Services\Push\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Phone notifications for /grafikas: subscribe/unsubscribe from the page,
 * the service worker + manifest (served by Laravel because the web root is
 * outside the repo), and the dispatcher that actually sends queued
 * messages. Sending must run in the web (PHP-FPM) context — the host's CLI
 * has no OpenSSL config for EC keys — so the scheduler calls dispatch() over HTTP.
 */
class PushController extends Controller
{
    public function subscribe(Request $request, string $tournament): JsonResponse
    {
        abort_unless(AppApiController::acceptsPush($tournament), 404);
        $data = $request->validate([
            'player' => 'required|string|max:120',
            'subscription.endpoint' => 'required|url|starts_with:https://|max:1000',
            'subscription.keys.p256dh' => 'required|string|max:200',
            'subscription.keys.auth' => 'required|string|max:100',
        ]);
        $endpoint = $data['subscription']['endpoint'];
        // Only real browser push services (we POST to this URL), and keys
        // with the exact shape Web Push needs, so a bad record can't be stored.
        abort_unless(self::isPushService($endpoint), 422, 'Nepalaikomas pranešimų adresas');
        $p256dh = WebPush::b64urlDecode($data['subscription']['keys']['p256dh']);
        $auth = WebPush::b64urlDecode($data['subscription']['keys']['auth']);
        abort_unless(strlen($p256dh) === 65 && $p256dh[0] === "\x04" && strlen($auth) === 16, 422, 'Netinkami pranešimų raktai');

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'tournament_external_id' => $tournament,
                'player' => $data['player'],
                'player_key' => PushSubscription::key($data['player']),
                'endpoint' => $endpoint,
                'p256dh' => $data['subscription']['keys']['p256dh'],
                'auth' => $data['subscription']['keys']['auth'],
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();

        return response()->json(['ok' => true]);
    }

    /** Sends queued messages. Called every minute by the scheduler with X-Push-Token. */
    public function dispatch(Request $request): JsonResponse
    {
        $expected = (string) config('services.push.dispatch_token');
        abort_unless($expected !== '' && hash_equals($expected, (string) $request->header('X-Push-Token')), 403);

        // "Go to your court" is useless 15 minutes late.
        PushMessage::where('status', 'pending')->where('created_at', '<', now()->subMinutes(15))->update(['status' => 'expired']);

        $sent = $failed = 0;
        $messages = PushMessage::with(['subscription', 'device'])->where('status', 'pending')->orderBy('id')->limit(300)->get();

        // App phones: one batched call to Expo's push service.
        $appMsgs = $messages->filter(fn ($m) => $m->app_device_id)->values();
        if ($appMsgs->isNotEmpty()) {
            $live = $appMsgs->filter(fn ($m) => $m->device)->values();
            $appMsgs->reject(fn ($m) => $m->device)->each->update(['status' => 'expired']);
            $results = ExpoPush::send($live->map(fn ($m) => [
                'to' => $m->device->expo_token,
                'title' => $m->title,
                'body' => $m->body,
                'data' => ['kind' => $m->kind, 'matchId' => $m->url, 'tournament' => $m->device->tournament_external_id],
            ])->all());
            foreach ($live as $i => $msg) {
                $res = $results[$i];
                if ($res['gone']) {
                    $msg->device->delete(); // cascades its messages
                    continue;
                }
                $attempts = $msg->attempts + 1;
                $msg->update($res['ok']
                    ? ['status' => 'sent', 'sent_at' => now(), 'attempts' => $attempts]
                    : ['status' => $attempts >= 3 ? 'failed' : 'pending', 'attempts' => $attempts]);
                $res['ok'] ? $sent++ : $failed++;
            }
        }

        $webMsgs = $messages->reject(fn ($m) => $m->app_device_id);
        $push = $webMsgs->isNotEmpty() ? self::webPush() : null;
        foreach ($webMsgs as $msg) {
            if (! $msg->subscription) {
                $msg->update(['status' => 'expired']);
                continue;
            }
            try {
                $res = $push->send($msg->subscription->toWebPush(), json_encode([
                    'title' => $msg->title,
                    'body' => $msg->body,
                    'url' => $msg->url,
                    'tag' => $msg->dedupe_key,
                ], JSON_UNESCAPED_UNICODE), 900);
            } catch (\Throwable $e) {
                // a broken subscription must not block everyone else's messages
                report($e);
                $msg->subscription->delete();
                $failed++;
                continue;
            }

            if ($res['gone']) {
                $msg->subscription->delete(); // cascades its messages
                continue;
            }
            $attempts = $msg->attempts + 1;
            $msg->update($res['ok']
                ? ['status' => 'sent', 'sent_at' => now(), 'attempts' => $attempts]
                : ['status' => $attempts >= 3 ? 'failed' : 'pending', 'attempts' => $attempts]);
            $res['ok'] ? $sent++ : $failed++;
        }

        return response()->json(['sent' => $sent, 'failed' => $failed]);
    }

    public function serviceWorker(): Response
    {
        return response(file_get_contents(resource_path('push/sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function manifest(string $tournament): JsonResponse
    {
        $name = ScheduleSnapshot::where('tournament_external_id', $tournament)->value('payload')['tournament']['name'] ?? 'Grafikas';

        return response()->json([
            'name' => $name,
            'short_name' => 'Grafikas',
            'start_url' => '/grafikas/'.$tournament,
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#0A2226',
            'theme_color' => '#0A2226',
            'icons' => [
                ['src' => route('push.icon', 192), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => route('push.icon', 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function icon(int $size): Response
    {
        abort_unless(in_array($size, [192, 512], true), 404);

        return response(file_get_contents(resource_path("push/icon-{$size}.png")), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /** Push services of Chrome/Android, Firefox, Edge/Windows and Safari/iOS. */
    public static function isPushService(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['port']) || isset($parts['user'])) {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');

        return in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
            || (bool) preg_match('/\.(push\.services\.mozilla\.com|notify\.windows\.com|push\.apple\.com)$/', $host);
    }

    public static function webPush(): WebPush
    {
        return new WebPush(
            (string) config('services.push.vapid_public'),
            (string) file_get_contents((string) config('services.push.vapid_private_path')),
            (string) config('services.push.subject'),
        );
    }
}
