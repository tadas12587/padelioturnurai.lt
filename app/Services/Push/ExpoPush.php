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
     * @param  array<int, array{to: string, title: string, body: string, data?: array}>  $messages
     * @return array<int, array{ok: bool, gone: bool}> one result per message, same order
     */
    public static function send(array $messages): array
    {
        $out = [];
        foreach (array_chunk($messages, 100) as $chunk) {
            $payload = array_map(fn ($m) => $m + ['sound' => 'default', 'priority' => 'high', 'channelId' => 'default'], $chunk);
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
