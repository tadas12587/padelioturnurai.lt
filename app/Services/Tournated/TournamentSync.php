<?php

namespace App\Services\Tournated;

use App\Models\ScheduleSnapshot;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pulls one tournament from Tournated into its ScheduleSnapshot — the same
 * record the public /grafikas page reads (previously filled by the PC-side
 * tools/overlay-push/schedule-push.js relay).
 *
 * Matches: official API first (view=full: court, time, players, side-aligned
 * sets). If it fails (club league 11532 still 502s) the public GraphQL is
 * used instead. For team tournaments GraphQL is also asked for the division
 * names ("Moterys B 1") the API does not have.
 */
class TournamentSync
{
    public function __construct(
        private readonly TournatedApi $api,
        private readonly PadelGraphql $graphql,
    ) {}

    public static function make(): self
    {
        return new self(TournatedApi::fromConfig(), PadelGraphql::fromConfig());
    }

    /** @return array{source: string, matches: int, warnings: string[]} */
    public function sync(string $tournamentId): array
    {
        $existing = ScheduleSnapshot::where('tournament_external_id', $tournamentId)->value('payload') ?? [];
        $warnings = [];

        // Structure — each part keeps its last known value if a call fails.
        $part = function (string $key, callable $fetch) use (&$warnings, $existing) {
            try {
                return $fetch();
            } catch (Throwable $e) {
                $warnings[] = "{$key}: {$e->getMessage()}";

                return $existing[$key] ?? [];
            }
        };

        $tournament = $part('tournament', fn () => $this->tournamentInfo($tournamentId));
        $groups = $part('groups', fn () => array_map(fn ($g) => [
            'group_id' => $g['group_id'],
            'category_id' => $g['category_id'] ?? null,
            'name' => trim((string) ($g['name'] ?? '')),
            'segment' => $g['segment'] ?? null,
            'entries' => array_map([MatchShape::class, 'entry'], $g['entries'] ?? []),
            'match_ids' => $g['match_ids'] ?? [],
        ], $this->api->list('groups', $tournamentId, ['include' => 'entries,matches'])));
        $draws = $part('draws', fn () => array_map(fn ($d) => [
            'draw_id' => $d['draw_id'],
            'category_id' => $d['category_id'] ?? null,
            'title' => $d['title'] ?? null,
            'segment' => $d['segment'] ?? null,
            'type' => $d['type'] ?? null,
            'size' => $d['size'] ?? null,
            'main_draw_id' => $d['main_draw_id'] ?? null,
            'entries' => array_map([MatchShape::class, 'entry'], $d['entries'] ?? []),
            'match_ids' => $d['match_ids'] ?? [],
        ], $this->api->list('draws', $tournamentId, ['include' => 'entries,matches'])));
        $standings = $part('standings', fn () => array_map(fn ($s) => [
            'category_id' => $s['category_id'] ?? null,
            'title' => $s['title'] ?? null,
            'source' => $s['source'] ?? null,
            'entries' => array_map([MatchShape::class, 'entry'], $s['entries'] ?? []),
        ], $this->api->list('standings', $tournamentId)));
        $teams = $part('teams', fn () => array_map(fn ($t) => [
            'team_id' => $t['team_id'],
            'title' => $t['team_name'] ?? null,
        ], $this->api->list('teams', $tournamentId)));

        [$matches, $source] = $this->matches($tournamentId, ! empty($teams), $warnings);

        ScheduleSnapshot::updateOrCreate(
            ['tournament_external_id' => $tournamentId],
            ['payload' => [
                'tournament' => $tournament,
                'matches' => $matches,
                'groups' => $groups,
                'draws' => $draws,
                'standings' => $standings,
                'teams' => $teams,
                'court_delays' => $existing['court_delays'] ?? [],
                'source' => $source,
                'synced_at' => now()->toIso8601String(),
            ]],
        );

        if ($warnings) {
            Log::warning("tournated:sync {$tournamentId}", $warnings);
        }

        return ['source' => $source, 'matches' => count($matches), 'warnings' => $warnings];
    }

    private function tournamentInfo(string $id): array
    {
        $t = $this->api->tournament($id);

        return [
            'tournament_id' => $t['tournament_id'] ?? (int) $id,
            'name' => $t['tournament_name'] ?? null,
            'tz' => $t['tournament_tz'] ?? 'Europe/Vilnius',
            'start' => $t['tournament_start_date'] ?? null,
            'end' => $t['tournament_end_date'] ?? null,
            'courts' => array_map(fn ($c) => ['court_id' => $c['court_id'], 'name' => $c['name']], $t['courts'] ?? []),
            'categories' => array_map(fn ($c) => ['category_id' => $c['category_id'] ?? $c['id'] ?? null, 'name' => $c['category_name'] ?? $c['name'] ?? null], $t['categories'] ?? []),
            'show_oop' => $t['show_oop'] ?? null,
        ];
    }

    /** @return array{0: array, 1: string} */
    private function matches(string $tournamentId, bool $teamEvent, array &$warnings): array
    {
        try {
            $matches = array_values(array_filter(
                array_map([MatchShape::class, 'fromApi'], $this->api->matchesFull($tournamentId)),
                fn ($m) => ! $m['is_bye'],
            ));
        } catch (Throwable $e) {
            $warnings[] = "matches API: {$e->getMessage()} — naudojamas GraphQL";
            try {
                return [$this->graphql->matches($tournamentId), 'graphql'];
            } catch (Throwable $g) {
                throw new RuntimeException("Mačų gauti nepavyko: API ({$e->getMessage()}), GraphQL ({$g->getMessage()})");
            }
        }

        if ($teamEvent) {
            try {
                $names = [];
                foreach ($this->graphql->matches($tournamentId) as $g) {
                    $names[$g['match_id']] = $g['division'];
                }
                foreach ($matches as &$m) {
                    $m['division'] = $names[$m['match_id']] ?? $m['division'];
                }
                unset($m);
            } catch (Throwable $e) {
                $warnings[] = "division names (GraphQL): {$e->getMessage()}";
            }
        }

        return [$matches, 'api'];
    }
}
