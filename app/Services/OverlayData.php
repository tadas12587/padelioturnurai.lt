<?php

namespace App\Services;

use App\Models\OverlaySnapshot;
use Illuminate\Support\Carbon;

/**
 * Reads tournament data from the locally-stored snapshot (pushed in by an
 * external bridge, because the production host cannot reach the Tournated API
 * directly) and computes group standings from it.
 *
 * Snapshot payload shape (as pushed to POST /overlay/ingest):
 *   {
 *     "title": "...",
 *     "categories": [ {"id":..,"category":{"id":..,"name":".."},"mde":..}, ... ],
 *     "groups_by_category": {
 *       "<categoryId>": [ {"id":..,"name":"..","segment":"..","entries":[..],"matches":[..]}, ... ]
 *     }
 *   }
 */
class OverlayData
{
    /** @return array<string,mixed> */
    private function payload(string $tournamentId): array
    {
        $snapshot = OverlaySnapshot::where('tournament_external_id', $tournamentId)->first();

        return $snapshot?->payload ?? [];
    }

    /**
     * Title + categories in the native Tournated shape (for the admin preview).
     *
     * @return array<string,mixed>
     */
    public function tournament(string $tournamentId): array
    {
        $payload = $this->payload($tournamentId);

        if (empty($payload)) {
            return [];
        }

        return [
            'title'              => $payload['title'] ?? null,
            'tournamentCategory' => $payload['categories'] ?? [],
        ];
    }

    /** @return array<int,mixed> */
    public function categories(string $tournamentId): array
    {
        return $this->payload($tournamentId)['categories'] ?? [];
    }

    /** @return array<int,mixed> */
    public function groups(string $tournamentId, int $categoryId): array
    {
        $byCategory = $this->payload($tournamentId)['groups_by_category'] ?? [];

        return $byCategory[(string) $categoryId] ?? [];
    }

    public function updatedAt(string $tournamentId): ?Carbon
    {
        return OverlaySnapshot::where('tournament_external_id', $tournamentId)->value('updated_at');
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
