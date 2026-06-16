<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TournatedClient
{
    const ENDPOINT = 'https://api.tournated.com/graphql';
    const ORIGIN   = 'https://play.padel.lt';
    const CACHE_TTL = 18; // seconds

    /**
     * Transport that POSTs the JSON body and returns the response body, or
     * null on failure. Defaults to a raw cURL call (see curlPost). Injectable
     * so tests can stub the network without hitting Tournated.
     *
     * @var (callable(string): ?string)|null
     */
    private $transport;

    /** @param (callable(string): ?string)|null $transport */
    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    /** @return array<int,mixed> */
    public function groups(int $categoryId): array
    {
        return Cache::remember("overlay.groups.$categoryId", self::CACHE_TTL, function () use ($categoryId) {
            $query = '{ groups(filter: { tournamentCategory: ' . $categoryId . ' }) {
                id name segment
                entries { id place registrationRequest { users { user { name surname } } } }
                matches { id status winner { id } }
            } }';

            $data = $this->graphql($query);

            return $data['groups'] ?? [];
        });
    }

    /** @return array<int,mixed> */
    public function categories(int $tournamentId): array
    {
        return Cache::remember("overlay.categories.$tournamentId", 300, function () use ($tournamentId) {
            $query = '{ tournament(id: ' . $tournamentId . ') {
                title tournamentCategory { id category { id name } mde }
            } }';

            $data = $this->graphql($query);

            return $data['tournament']['tournamentCategory'] ?? [];
        });
    }

    /**
     * Fetch the raw tournament node (title + categories) for the admin preview.
     * Short cache so a freshly-saved tournament id reflects quickly.
     *
     * @return array<string,mixed>
     */
    public function tournament(int $tournamentId): array
    {
        return Cache::remember("overlay.tournament.$tournamentId", 30, function () use ($tournamentId) {
            $query = '{ tournament(id: ' . $tournamentId . ') {
                title tournamentCategory { id category { id name } mde }
            } }';

            $data = $this->graphql($query);

            return $data['tournament'] ?? [];
        });
    }

    /** @return array<string,mixed> */
    private function graphql(string $query): array
    {
        $body = ($this->transport ?? [$this, 'curlPost'])(json_encode(['query' => $query]));

        if ($body === null) {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? ($decoded['data'] ?? []) : [];
    }

    /**
     * Raw cURL POST to the GraphQL endpoint, forced over IPv4.
     *
     * The production shared host (freehosting.lt) has no working IPv6 route,
     * so the default IPv6-first attempt hangs until timeout. Laravel/Guzzle on
     * this host does not honour `force_ip_resolve` nor the `curl` option array,
     * so we issue the request with the cURL extension directly — verified
     * working with CURLOPT_IPRESOLVE_V4 (curl_errno 0, HTTP 200).
     */
    private function curlPost(string $payload): ?string
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Origin: ' . self::ORIGIN,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
        ]);

        $body   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            Log::warning('Tournated cURL error ' . $errno . ': ' . curl_strerror($errno));
            return null;
        }

        if ($status >= 400) {
            Log::warning('Tournated request failed: HTTP ' . $status);
            return null;
        }

        return is_string($body) ? $body : null;
    }

    /**
     * Port of the documented calcStats: wins from winner.id; losses/played
     * resolved only when every round-robin match is complete.
     *
     * @param  array<string,mixed>  $group
     * @return list<array<string,mixed>>
     */
    public function computeStandings(array $group): array
    {
        $entries = $group['entries'] ?? [];
        $matches = $group['matches'] ?? [];

        $wins = [];
        foreach ($entries as $e) {
            $wins[$e['id']] = 0;
        }

        $completed = array_filter($matches, fn ($m) => ($m['status'] ?? null) === 'completed');
        foreach ($completed as $m) {
            $winnerId = $m['winner']['id'] ?? null;
            if ($winnerId !== null && array_key_exists($winnerId, $wins)) {
                $wins[$winnerId]++;
            }
        }

        $n = count($entries);
        $totalPossible = $n > 1 ? $n * ($n - 1) / 2 : 0;
        $allDone = count($completed) >= $totalPossible && $totalPossible > 0;

        $rows = array_map(function ($e) use ($wins, $allDone, $n) {
            $w = $wins[$e['id']] ?? 0;
            return [
                'id'     => $e['id'],
                'place'  => $e['place'] ?? null,
                'name'   => $this->pairName($e),
                'wins'   => $w,
                'losses' => $allDone ? ($n - 1 - $w) : null,
                'played' => $allDone ? ($n - 1) : $w,
            ];
        }, $entries);

        usort($rows, fn ($a, $b) => ($a['place'] ?? 99) <=> ($b['place'] ?? 99));

        return array_values($rows);
    }

    /** @param array<string,mixed> $entry */
    private function pairName(array $entry): string
    {
        $users = $entry['registrationRequest']['users'] ?? [];
        $fmt = fn ($u) => trim(($u['user']['name'] ?? '') . ' ' . ($u['user']['surname'] ?? ''));

        if (count($users) >= 2) {
            return $fmt($users[0]) . ' / ' . $fmt($users[1]);
        }

        return isset($users[0]) ? $fmt($users[0]) : '???';
    }
}
