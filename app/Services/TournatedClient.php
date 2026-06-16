<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TournatedClient
{
    const ENDPOINT = 'https://api.tournated.com/graphql';
    const ORIGIN   = 'https://play.padel.lt';
    const CACHE_TTL = 18; // seconds

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
        try {
            // Force IPv4 — the production shared host has no working IPv6 route,
            // so the default IPv6-first attempt hangs until timeout. Guzzle's
            // `force_ip_resolve` is ignored on this host, so set the raw cURL
            // option directly (verified working: CURLOPT_IPRESOLVE_V4).
            $res = Http::timeout(5)
                ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->withHeaders(['Origin' => self::ORIGIN])
                ->post(self::ENDPOINT, ['query' => $query]);

            if ($res->failed()) {
                Log::warning('Tournated request failed: ' . $res->status());
                return [];
            }

            return $res->json('data') ?? [];
        } catch (\Throwable $e) {
            Log::warning('Tournated request error: ' . $e->getMessage());
            return [];
        }
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
