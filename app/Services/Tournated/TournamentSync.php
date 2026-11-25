<?php

namespace App\Services\Tournated;

use App\Models\ScheduleSnapshot;
use App\Models\Tournament;
use App\Services\Push\PushEvents;
use Carbon\CarbonImmutable;
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

        // city / country per player for the TV screen — slow-changing, refreshed every 30 min
        $places = $existing['places'] ?? [];
        $placesAt = $existing['places_at'] ?? null;
        if (! $placesAt || CarbonImmutable::parse($placesAt)->diffInMinutes(now(), true) >= 30) {
            $places = $part('places', fn () => $this->places($tournamentId));
            $placesAt = now()->toIso8601String();
        }

        [$matches, $source] = $this->matches($tournamentId, ! empty($teams), $warnings);
        $matches = self::stampResults($matches, $existing['matches'] ?? [], ! empty($existing));
        $pushOn = Tournament::where('tournated_id', $tournamentId)->value('push_enabled') ?? true;
        if ($pushOn && ! empty($existing['matches'])) {
            try {
                $matches = PushEvents::detect($tournamentId, $existing['matches'], $matches,
                    ($existing['source'] ?? null) === $source, $tournament['tz'] ?? 'Europe/Vilnius');
            } catch (Throwable $e) {
                $warnings[] = "push: {$e->getMessage()}"; // notifications must never break the sync
            }
        }

        $dates = array_filter(array_column($matches, 'date'));
        $tournament['date'] = $dates ? min($dates) : (isset($tournament['start'])
            ? CarbonImmutable::parse($tournament['start'])->setTimezone($tournament['tz'] ?? 'Europe/Vilnius')->toDateString()
            : null);

        ScheduleSnapshot::updateOrCreate(
            ['tournament_external_id' => $tournamentId],
            ['payload' => [
                'tournament' => $tournament,
                'matches' => $matches,
                'groups' => $groups,
                'draws' => $draws,
                'standings' => $standings,
                'teams' => $teams,
                'places' => $places,
                'places_at' => $placesAt,
                'court_delays' => $existing['court_delays'] ?? [],
                'source' => $source,
                'synced_at' => now()->toIso8601String(),
            ]],
        );

        // Participant count for the homepage statistics, unless typed by hand.
        $people = [];
        foreach ($matches as $m) {
            foreach ($m['participants'] ?? [] as $p) {
                $people[mb_strtolower(trim(($p['name'] ?? '').' '.($p['surname'] ?? '')))] = true;
            }
        }
        Tournament::where('tournated_id', $tournamentId)->where('participants_auto', true)
            ->update(['participants_count' => count(array_filter(array_keys($people), fn ($k) => $k !== ''))]);

        if ($warnings) {
            Log::warning("tournated:sync {$tournamentId}", $warnings);
        }

        return ['source' => $source, 'matches' => count($matches), 'warnings' => $warnings];
    }

    /**
     * Remember when each result first appeared — that moment, not Tournated's
     * updated_at (which moves on later edits), drives CourtDelays. Results
     * present on the very first sync, or several on one court in the same
     * cycle (entered in a batch), are flagged so they are not trusted.
     */
    private static function stampResults(array $matches, array $previous, bool $hadSnapshot): array
    {
        $before = [];
        foreach ($previous as $p) {
            $before[$p['match_id']] = $p;
        }
        $now = now()->toIso8601String();
        $newPerCourt = [];

        foreach ($matches as $i => $m) {
            $played = ! empty($m['sets']) || ! empty($m['winner_side']) || ! empty($m['is_walkover']);
            $old = $before[$m['match_id']] ?? null;
            if (! $played) {
                continue;
            }
            if ($old && array_key_exists('result_seen_at', $old)) {
                $matches[$i]['result_seen_at'] = $old['result_seen_at'];
                $matches[$i]['result_batch'] = $old['result_batch'] ?? false;
                continue;
            }
            $wasPlayed = $old && (! empty($old['sets']) || ! empty($old['winner_side']) || ! empty($old['is_walkover']));
            $live = $hadSnapshot && ! $wasPlayed;
            $matches[$i]['result_seen_at'] = $live ? $now : null;
            $matches[$i]['result_batch'] = false;
            if ($live) {
                $newPerCourt[$m['court']['name'] ?? '?'][] = $i;
            }
        }
        foreach ($newPerCourt as $idx) {
            if (count($idx) > 1) {
                foreach ($idx as $i) {
                    $matches[$i]['result_batch'] = true;
                }
            }
        }

        return $matches;
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
            // the organiser's logo, if one was uploaded in Tournated (shown on the TV schedule)
            'logo' => is_string($t['logo'] ?? null) && str_starts_with($t['logo'], 'https://') ? $t['logo'] : null,
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
                return [self::withoutTieContainers($this->graphql->matches($tournamentId)), 'graphql'];
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

        return [self::withoutTieContainers($matches), 'api'];
    }

    /**
     * Club-league events also carry the club-vs-club "tie" itself as a match row:
     * no time or court, and every player of both clubs as participants (32+).
     * It is not a game anyone plays, so it must not show up as a player's
     * "next match". A real padel match has at most two players per side.
     */
    private static function withoutTieContainers(array $matches): array
    {
        return array_values(array_filter($matches, function ($m) {
            $perSide = array_count_values(array_map(fn ($p) => (string) ($p['side'] ?? ''), $m['participants'] ?? []));

            return ($perSide ? max($perSide) : 0) <= 2;
        }));
    }

    /**
     * "jonas petraitis" => "Klaipėda" (or a foreign country, e.g. "Latvija").
     * Only the place is kept — never e-mails, phones or birthdates.
     */
    private function places(string $tournamentId): array
    {
        $city = [];
        foreach ($this->api->list('players', $tournamentId) as $p) {
            if (! empty($p['player_id'])) {
                $city[$p['player_id']] = self::cityName((string) ($p['city'] ?? ''));
            }
        }
        $t = $this->api->get("/tournaments/{$tournamentId}", ['include' => 'participants']);
        $out = [];
        foreach (($t['data'] ?? $t)['participants'] ?? [] as $p) {
            $key = self::personKey(trim(($p['name'] ?? '').' '.($p['surname'] ?? '')));
            $place = ($city[$p['player_id'] ?? 0] ?? null) ?: self::foreignCountry($p['country'] ?? null);
            if ($key !== '' && $place) {
                $out[$key] = $place;
            }
        }

        return $out;
    }

    /** Lower-case, no diacritics, single spaces — the key the TV page looks names up by. */
    public static function personKey(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(\Illuminate\Support\Str::ascii($name))));
    }

    private const CITIES = [
        'klaipeda' => 'Klaipėda', 'klp' => 'Klaipėda', 'vilnius' => 'Vilnius', 'vln' => 'Vilnius', 'kaunas' => 'Kaunas',
        'siauliai' => 'Šiauliai', 'panevezys' => 'Panevėžys', 'palanga' => 'Palanga', 'plunge' => 'Plungė',
        'mazeikiai' => 'Mažeikiai', 'gargzdai' => 'Gargždai', 'telsiai' => 'Telšiai', 'kretinga' => 'Kretinga',
        'silute' => 'Šilutė', 'marijampole' => 'Marijampolė', 'alytus' => 'Alytus', 'utena' => 'Utena',
        'neringa' => 'Neringa', 'jonava' => 'Jonava', 'kedainiai' => 'Kėdainiai', 'taurage' => 'Tauragė',
    ];

    private static function cityName(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || mb_strlen($raw) > 40) {
            return null;
        }
        $k = self::personKey($raw);

        return self::CITIES[$k] ?? mb_convert_case($raw, MB_CASE_TITLE);
    }

    private const COUNTRIES = [
        'LV' => 'Latvija', 'EE' => 'Estija', 'PL' => 'Lenkija', 'DE' => 'Vokietija', 'ES' => 'Ispanija', 'FI' => 'Suomija',
        'SE' => 'Švedija', 'NO' => 'Norvegija', 'DK' => 'Danija', 'GB' => 'D. Britanija', 'UA' => 'Ukraina', 'IT' => 'Italija',
        'FR' => 'Prancūzija', 'NL' => 'Nyderlandai', 'BY' => 'Baltarusija', 'US' => 'JAV', 'PT' => 'Portugalija',
    ];

    /** Only a foreign country says something on a Lithuanian tournament screen. */
    private static function foreignCountry(mixed $c): ?string
    {
        $c = strtoupper(trim((string) (is_array($c) ? ($c['code'] ?? $c['iso2'] ?? '') : $c)));
        if ($c === '' || $c === 'LT' || $c === 'LITHUANIA') {
            return null;
        }

        return self::COUNTRIES[$c] ?? (strlen($c) === 2 ? $c : mb_convert_case(strtolower($c), MB_CASE_TITLE));
    }
}
