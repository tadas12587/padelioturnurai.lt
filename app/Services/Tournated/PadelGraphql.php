<?php

namespace App\Services\Tournated;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The public play.padel.lt GraphQL the Tournated website itself uses for its
 * "Order of play" page. Unofficial — used only as a fallback and for
 * club-league division names. Port of tools/overlay-push/schedule-push.js.
 */
class PadelGraphql
{
    private const QUERY = <<<'GQL'
query tournamentAllMatches($filter: ListMatchesInput) {
  tournamentAllMatches: tournamentAllMatchesPublic(filter: $filter) {
    matchesArray {
      matches {
        id date time status teamScore name
        isBye isWalkover isDisqualified isMatchInProgress isMatchTie
        court { id name }
        group { id name segment }
        team1Entry { user { id name surname } team { id title image } }
        team2Entry { user { id name surname } team { id title image } }
        teamWinner { user { id } }
      }
    }
  }
}
GQL;

    public function __construct(private readonly string $url) {}

    public static function fromConfig(): self
    {
        return new self((string) config('services.tournated.graphql_url'));
    }

    /** All matches, already in the internal schedule match shape. */
    public function matches(string $tournamentId): array
    {
        $res = Http::acceptJson()->timeout(30)->post($this->url, [
            'operationName' => 'tournamentAllMatches',
            'variables' => ['filter' => ['tournament' => (int) $tournamentId]],
            'query' => self::QUERY,
        ]);
        if (! $res->successful()) {
            throw new RuntimeException("GraphQL: HTTP {$res->status()}");
        }
        if ($res->json('errors')) {
            throw new RuntimeException('GraphQL: '.mb_substr(json_encode($res->json('errors')), 0, 200));
        }

        $out = [];
        foreach ($res->json('data.tournamentAllMatches.matchesArray') ?? [] as $group) {
            foreach ($group['matches'] ?? [] as $m) {
                $out[] = $this->normalise($m);
            }
        }

        return $out;
    }

    private function normalise(array $m): array
    {
        $rawSets = ($m['status'] ?? null) === 'completed' ? self::parseTeamScore($m['teamScore'] ?? null) : [];
        $trueWinner = self::winnerFromTeamWinner($m);

        // teamScore's left/right order does not reliably match team1/team2
        // (confirmed 2026-09-12) — teamWinner is the trustworthy signal, so
        // flip the sets when they disagree.
        $sets = $rawSets;
        if ($trueWinner && $rawSets) {
            $implied = MatchShape::winnerFromSets($rawSets);
            if ($implied && $implied !== $trueWinner) {
                $sets = array_map(fn ($s) => ['side1' => $s['side2'], 'side2' => $s['side1']], $rawSets);
            }
        }

        return [
            'match_id' => $m['id'],
            'date' => isset($m['date']) ? substr((string) $m['date'], 0, 10) : null,
            'time' => $m['time'] ?? null,
            'court' => isset($m['court']) ? ['court_id' => $m['court']['id'], 'name' => $m['court']['name']] : null,
            'division' => $m['name'] ?? null,
            'team1' => self::entryTeam($m['team1Entry'] ?? []),
            'team2' => self::entryTeam($m['team2Entry'] ?? []),
            'participants' => array_merge(self::entryPlayers($m['team1Entry'] ?? [], 1), self::entryPlayers($m['team2Entry'] ?? [], 2)),
            'sets' => $sets,
            'winner_side' => $trueWinner ?? MatchShape::winnerFromSets($sets),
            'is_bye' => (bool) ($m['isBye'] ?? false),
            'is_walkover' => (bool) ($m['isWalkover'] ?? false),
            'is_disqualified' => (bool) ($m['isDisqualified'] ?? false),
            'is_match_tie' => (bool) ($m['isMatchTie'] ?? false),
            'is_match_in_progress' => (bool) ($m['isMatchInProgress'] ?? false),
            'updated_at' => now()->toIso8601String(),
            'group_name' => $m['group']['name'] ?? null,
        ];
    }

    /** "6:3 6:1" or "7:5 6:7 [10:5]" -> [['side1'=>6,'side2'=>3], ...] */
    public static function parseTeamScore(?string $ts): array
    {
        $sets = [];
        foreach (preg_split('/\s+/', trim((string) $ts), -1, PREG_SPLIT_NO_EMPTY) as $tok) {
            $parts = explode(':', str_replace(['[', ']'], '', $tok));
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $sets[] = ['side1' => (int) $parts[0], 'side2' => (int) $parts[1]];
            }
        }

        return $sets;
    }

    private static function winnerFromTeamWinner(array $m): ?int
    {
        $winners = array_filter(array_map(fn ($w) => $w['user']['id'] ?? null, $m['teamWinner'] ?? []));
        if (! $winners) {
            return null;
        }
        foreach ([1 => 'team1Entry', 2 => 'team2Entry'] as $side => $key) {
            $ids = array_filter(array_map(fn ($e) => $e['user']['id'] ?? null, $m[$key] ?? []));
            if ($ids && ! array_diff($ids, $winners)) {
                return $side;
            }
        }

        return null;
    }

    private static function entryTeam(array $entry): ?array
    {
        $team = $entry[0]['team'] ?? null;

        return $team ? ['team_id' => $team['id'], 'title' => $team['title'], 'image' => $team['image'] ?? null] : null;
    }

    private static function entryPlayers(array $entry, int $side): array
    {
        return array_map(fn ($e) => [
            'side' => $side,
            'name' => trim((string) ($e['user']['name'] ?? '')),
            'surname' => trim((string) ($e['user']['surname'] ?? '')),
        ], $entry);
    }
}
