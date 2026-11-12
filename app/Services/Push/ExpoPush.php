<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Http;

/**
 * Sends notifications to the "Padelio turnyrai" app through Expo's push
 * service (it forwards to FCM / APNs with the credentials stored in EAS).
 * https://docs.expo.dev/push-notifications/sending-notifications/
 */
class ExpoPush
{
    private const URL = 'https://exp.host/--/api/v2/push/send';

    public static function isToken(string $token): bool
    {
        return (bool) preg_match('/^Expo(nent)?PushToken\[[A-Za-z0-9_\-]{10,}\]$/', $token);
    }

    /**
     * "Tavo eilė" for the player goes out loud: the app's own sound, the
     * Android "eile" channel (max importance) and iOS Time Sensitive, which
     * breaks through Focus modes. Everything else is a normal notification.
     */
    public static function payload(array $m): array
    {
        $urgent = (bool) ($m['urgent'] ?? false);
        unset($m['urgent']);

        return $m + ($urgent
            ? ['sound' => 'eile.wav', 'priority' => 'high', 'channelId' => 'eile', 'interruptionLevel' => 'time-sensitive']
            : ['sound' => 'default', 'priority' => 'high', 'channelId' => 'default']);
    }

    /**
     * @param  array<int, array{to: string, title: string, body: string, data?: array, urgent?: bool}>  $messages
     * @return array<int, array{ok: bool, gone: bool}> one result per message, same order
     */
    public static function send(array $messages): array
    {
        $out = [];
        foreach (array_chunk($messages, 100) as $chunk) {
            $payload = array_map([self::class, 'payload'], $chunk);
            try {
                $res = Http::acceptJson()->timeout(15)->post(self::URL, $payload);
                $tickets = $res->successful() ? ($res->json('data') ?? []) : [];
            } catch (\Throwable $e) {
                report($e);
                $tickets = [];
            }
            foreach ($chunk as $i => $m) {
                $t = $tickets[$i] ?? null;
                $out[] = [
                    'ok' => ($t['status'] ?? null) === 'ok',
                    'gone' => ($t['details']['error'] ?? null) === 'DeviceNotRegistered',
                ];
            }
        }

        return $out;
    }
}
