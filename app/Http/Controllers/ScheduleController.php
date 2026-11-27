<?php

namespace App\Http\Controllers;

use App\Services\Tournated\TournamentSync;
use App\Models\EntryList;
use App\Models\ScheduleSnapshot;
use App\Models\Tournament;
use App\Services\Tournated\CourtDelays;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public schedule / results / group-tables page for a tournament. The snapshot
 * is filled by the site itself (`tournated:sync`, App\Services\Tournated) or,
 * as a fallback, pushed by the PC-side `schedule-push.js` relay via ingest().
 *
 * "Division" labels (e.g. "Moterys TOP") are NOT a Tournated concept for this
 * club-league format (Tournated only has one catch-all category). They come
 * from the manually imported Excel entry list (App\Models\EntryList, same
 * source the draw board uses) and are matched onto live matches by pair name.
 */
class ScheduleController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $expected = config('services.overlay.ingest_token');

        if (! $expected || ! hash_equals($expected, (string) $request->header('X-Overlay-Token'))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'tournament_id' => 'required|string',
            'tournament'    => 'array',
            'matches'       => 'array',
            'groups'        => 'array',
            'standings'     => 'array',
        ]);

        // Merge incoming matches by match_id onto whatever is already stored,
        // rather than replacing wholesale. Each push (e.g. a GitHub Actions
        // run) is a fresh, stateless process that may only have refreshed a
        // subset of matches (Tournated's /matches 502s per-match sometimes —
        // see tools/overlay-push/schedule-push.js) — merging means a match
        // that failed to refresh this time keeps its last known state instead
        // of disappearing from the public page.
        $existing = ScheduleSnapshot::where('tournament_external_id', $validated['tournament_id'])->value('payload') ?? [];
        $byId = collect($existing['matches'] ?? [])->keyBy('match_id');
        foreach ($validated['matches'] ?? [] as $m) {
            if (isset($m['match_id'])) {
                $byId->put($m['match_id'], $m);
            }
        }

        ScheduleSnapshot::updateOrCreate(
            ['tournament_external_id' => $validated['tournament_id']],
            ['payload' => [
                'tournament' => $validated['tournament'] ?? ($existing['tournament'] ?? []),
                'matches'    => $byId->values()->all(),
                'groups'     => ! empty($validated['groups']) ? $validated['groups'] : ($existing['groups'] ?? []),
                'standings'  => ! empty($validated['standings']) ? $validated['standings'] : ($existing['standings'] ?? []),
                'court_delays' => $existing['court_delays'] ?? [],
                'synced_at'  => now()->toIso8601String(),
            ]],
        );

        return response()->json(['ok' => true]);
    }

    public function show(string $tournamentExternalId)
    {
        $snapshot = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->first();
        $site = Tournament::where('tournated_id', $tournamentExternalId)->first();

        abort_if(! $snapshot || ($site && ! $site->show_schedule), 404);

        $payload = $snapshot->payload ?? [];
        $matches = array_values(array_filter($payload['matches'] ?? [], [self::class, 'isUsableMatch']));
        $groups = self::publicGroups($payload);
        $draws = self::publicDraws($payload);

        $divisionByPair = $this->divisionLookup($tournamentExternalId);
        $matches = array_map(fn (array $m) => $this->tagDivision($m, $divisionByPair), $matches);
        [$matches, $courtDelays] = $this->withDelays($payload, $matches);

        // Sort by date+time, then court, so the grid renders in a stable order.
        usort($matches, function (array $a, array $b) {
            $ka = ($a['date'] ?? '') . ' ' . ($a['time'] ?? '') . ' ' . ($a['court']['court_id'] ?? 0);
            $kb = ($b['date'] ?? '') . ' ' . ($b['time'] ?? '') . ' ' . ($b['court']['court_id'] ?? 0);

            return $ka <=> $kb;
        });

        $courts = collect($matches)->pluck('court.name')->filter()->unique()->values();
        // Same division played as multiple legs (e.g. "Moterys B 1"/"Moterys B 2")
        // is one level, not two — merge the trailing leg number for the pill list.
        $divisions = collect($matches)
            ->pluck('division')
            ->filter()
            ->map(fn ($d) => preg_replace('/\s+\d+$/', '', $d))
            ->unique()
            ->sort()
            ->values();
        $ties = self::publicTies($payload);
        $clubs = collect(array_merge($matches, $ties))
            ->flatMap(fn ($m) => [$m['team1']['title'] ?? null, $m['team2']['title'] ?? null])
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $playedCount = collect($matches)->filter(fn ($m) => $this->isPlayed($m))->count();

        return view('pages.schedule', [
            'tournamentId' => $tournamentExternalId,
            'tournament'   => $payload['tournament'] ?? [],
            'matches'      => $matches,
            'groups'       => $groups,
            'draws'        => $draws,
            'ties'         => $ties,
            'leaguePoints' => self::publicLeaguePoints($payload),
            'syncedAt'     => $payload['synced_at'] ?? null,
            'stats'        => [
                'courts'    => $courts->count(),
                'divisions' => $divisions->count(),
                'clubs'     => $clubs->count(),
                'matches'   => count($matches),
                'played'    => $playedCount,
            ],
            'divisionList' => $divisions,
            'clubList'     => $clubs,
            'matchesByClub' => $this->groupByClub($matches, $clubs),
            'courtDelays'  => $courtDelays,
            'pushEnabled'  => ! $site || $site->push_enabled,
        ]);
    }

    /**
     * Automatic per-court estimate (CourtDelays) for today's matches, with a
     * delay the organizer set by hand on the delays page taking precedence.
     *
     * @return array{0: array, 1: array<string,int>}
     */
    private function withDelays(array $payload, array $matches): array
    {
        $manual = array_filter($payload['court_delays'] ?? [], fn ($v) => (int) $v > 0);
        $auto = CourtDelays::apply($matches, CarbonImmutable::now(), $payload['tournament']['tz'] ?? 'Europe/Vilnius');

        // A manual delay shrinks by the spare time of every planned break on
        // that court (reserve hour, next day), so a later session isn't pushed.
        $delayFor = [];
        $byCourt = [];
        foreach ($auto['matches'] as $i => $m) {
            $court = $m['court']['name'] ?? '';
            if (isset($manual[$court]) && ! empty($m['time'])) {
                $byCourt[$court][] = $i;
            }
        }
        foreach ($byCourt as $court => $idx) {
            $key = fn ($i) => ($auto['matches'][$i]['date'] ?? '').' '.substr($auto['matches'][$i]['time'], 0, 5);
            usort($idx, fn ($a, $b) => strcmp($key($a), $key($b)));
            $slot = CourtDelays::slot(array_map(fn ($i) => substr($auto['matches'][$i]['time'], 0, 5), $idx));
            $left = (int) $manual[$court];
            $prev = null;
            foreach ($idx as $i) {
                $at = CarbonImmutable::parse($key($i));
                if ($prev !== null) {
                    $gap = (int) $prev->diffInMinutes($at);
                    if (CourtDelays::isBreak($gap, $slot)) {
                        $left = max(0, $left - ($gap - $slot));
                    }
                }
                $delayFor[$i] = $left;
                $prev = $at;
            }
        }

        $matches = [];
        foreach ($auto['matches'] as $i => $m) {
            $court = $m['court']['name'] ?? '';
            if (isset($manual[$court])) {
                unset($m['eta'], $m['eta_delay']);
                $d = $delayFor[$i] ?? 0;
                if ($d > 0 && ! $this->isPlayed($m) && ! empty($m['time'])) {
                    $t = CarbonImmutable::createFromFormat('H:i', substr($m['time'], 0, 5))->addMinutes($d);
                    $m['eta'] = $t->format('H:i');
                    $m['eta_delay'] = $d;
                }
            }
            $matches[$i] = $m;
        }

        return [$matches, array_replace($auto['courts'], $manual)];
    }

    /** JSON the page polls while open, to pick up new results without a full reload. */
    public function data(string $tournamentExternalId): JsonResponse
    {
        $snapshot = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->first();
        abort_if(! $snapshot || Tournament::where('tournated_id', $tournamentExternalId)->where('show_schedule', false)->exists(), 404);

        $payload = $snapshot->payload ?? [];
        $matches = array_values(array_filter($payload['matches'] ?? [], [self::class, 'isUsableMatch']));
        $divisionByPair = $this->divisionLookup($tournamentExternalId);
        $matches = array_map(fn (array $m) => $this->tagDivision($m, $divisionByPair), $matches);
        [$matches, $courtDelays] = $this->withDelays($payload, $matches);

        return response()->json([
            'matches'      => $matches,
            'groups'       => self::publicGroups($payload),
            'draws'        => self::publicDraws($payload),
            'ties'         => self::publicTies($payload),
            'league_points' => self::publicLeaguePoints($payload),
            'synced_at'    => $payload['synced_at'] ?? null,
            'court_delays' => $courtDelays,
        ]);
    }

    /**
     * Full-screen TV schedule for the venue: the chosen courts side by side —
     * last result, playing now, then the next matches with their estimated start.
     * ?ekranas=2 = the admin's 2nd screen; ?kortai=4,5,6 / ?colai= / ?m= override per TV.
     */
    public function tv(Request $request, string $tournamentExternalId)
    {
        return $this->tvPage($request, $tournamentExternalId, (int) $request->query('ekranas', 0));
    }

    /** padelioturnyrai.lt/tv/2 — screen 2 of today's tournament (short to type with a remote). */
    public function tvScreen(Request $request, int $screen)
    {
        $t = $this->tvTournaments()->firstWhere('tv_phase', 'live');
        if (! $t) {
            return redirect()->route('schedule.tv.home');
        }

        return $this->tvPage($request, (string) $t->tournated_id, $screen);
    }

    private function tvPage(Request $request, string $tournamentExternalId, int $screen)
    {
        $snapshot = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->first();
        $tournament = Tournament::with('translations')->where('tournated_id', $tournamentExternalId)->first();
        abort_if(! $snapshot || ($tournament && ! $tournament->show_schedule), 404);
        $screenName = $screen > 0 ? ($tournament?->tvScreens()[$screen - 1]['name'] ?? '') : '';

        $query = array_filter($request->only(['kortai', 'colai', 'm']), fn ($v) => is_string($v) && $v !== '');
        if ($screen > 0) {
            $query['ekranas'] = $screen;
        }

        return view('pages.tv', [
            'id' => $tournamentExternalId,
            'title' => ($tournament?->translation('lt')?->title ?? ($snapshot->payload['tournament']['name'] ?? 'Turnyras')).($screenName !== '' ? ' · '.$screenName : ''),
            'dataQuery' => http_build_query($query),
            'logo' => $snapshot->payload['tournament']['logo'] ?? null,
        ]);
    }

    /** Our tournaments with a public schedule, each tagged live / upcoming / past. */
    private function tvTournaments()
    {
        $withSchedule = ScheduleSnapshot::pluck('tournament_external_id')->all();
        $today = now('Europe/Vilnius')->toDateString();

        return Tournament::with('translations')
            ->whereNotNull('tournated_id')->where('show_schedule', true)
            ->whereIn('tournated_id', $withSchedule)
            ->get()
            ->map(function (Tournament $t) use ($today) {
                $start = $t->date_start?->toDateString();
                $end = ($t->date_end ?? $t->date_start)?->toDateString();
                $t->setAttribute('tv_phase', $end && $end < $today ? 'past' : ($start && $start > $today ? 'upcoming' : 'live'));

                return $t;
            });
    }

    /**
     * padelioturnyrai.lt/tv — short enough to type with a TV remote. On a
     * tournament day with one screen it opens that schedule straight away;
     * otherwise a list with big buttons (screens, demo mode).
     */
    public function tvHome()
    {
        $all = $this->tvTournaments();
        $live = $all->where('tv_phase', 'live')->values();
        if ($live->count() === 1 && count($live->first()->tvScreens()) <= 1) {
            return redirect()->route('schedule.tv', $live->first()->tournated_id);
        }

        $list = $live
            ->concat($all->where('tv_phase', 'upcoming')->sortBy('date_start'))
            ->concat($all->where('tv_phase', 'past')->sortByDesc('date_start')->take(4))
            ->values();

        return view('pages.tv-home', ['tournaments' => $list, 'courtNames' => fn (Tournament $t) => self::courtNames((string) $t->tournated_id)]);
    }

    /** Tournated court ID => name ("52836" => "Vidus 1"), as in the schedule. */
    public static function courtNames(string $tournamentExternalId): array
    {
        $payload = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->value('payload') ?? [];
        $names = [];
        foreach ($payload['tournament']['courts'] ?? [] as $c) {
            if (isset($c['court_id'])) {
                $names[(int) $c['court_id']] = (string) ($c['name'] ?? '');
            }
        }
        foreach ($payload['matches'] ?? [] as $m) {
            if (isset($m['court']['court_id'])) {
                $names[(int) $m['court']['court_id']] ??= (string) ($m['court']['name'] ?? '');
            }
        }
        uasort($names, fn ($a, $b) => strnatcasecmp($a, $b));

        return $names;
    }

    public function tvData(Request $request, string $tournamentExternalId): JsonResponse
    {
        $base = $this->data($tournamentExternalId)->getData(true);
        $payload = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->value('payload') ?? [];
        $tournament = Tournament::where('tournated_id', $tournamentExternalId)->first();

        // which courts: an admin screen (by Tournated court ID), else a typed number list
        $courtIds = [];
        $courtNumbers = [];
        $screen = (int) $request->query('ekranas', 0);
        $override = $request->query('kortai');
        $screenCfg = $screen > 0 && $tournament ? ($tournament->tvScreens()[$screen - 1] ?? null) : null;
        if ($screen > 0 && $tournament) {
            $courtIds = $screenCfg['courts'] ?? [];
        } elseif (is_string($override) && $override !== '') {
            $courtNumbers = (new Tournament)->tvCourtNumbers($override);
        } elseif ($tournament && ! $tournament->tvScreens()) {
            $courtNumbers = $tournament->tvCourtNumbers();
        }
        $onScreen = function (array $m) use ($courtIds, $courtNumbers): bool {
            if ($courtIds) {
                return in_array((int) ($m['court']['court_id'] ?? 0), $courtIds, true);
            }
            if ($courtNumbers) {
                preg_match('/\d+/', (string) ($m['court']['name'] ?? ''), $n);

                return in_array((int) ($n[0] ?? 0), $courtNumbers, true);
            }

            return true;
        };

        return response()->json([
            'matches' => $base['matches'],
            'synced_at' => $base['synced_at'],
            // the TV takes its clock from here: old TVs often have the wrong time or time zone
            'server_ms' => (int) round(microtime(true) * 1000),
            'tz_offset_min' => now('Europe/Vilnius')->utcOffset(),
            'court_ids' => $courtIds,
            'courts' => $courtNumbers,
            'places' => ($tournament?->tv_show_place ?? true) ? (object) self::tvPlaces($base['matches'], $payload['places'] ?? [], $onScreen) : (object) [],
            // size / distance: ?colai=&m= on the URL, else this screen's, else the tournament's
            'font_share' => Tournament::tvFontShare(
                (int) ($request->query('colai') ?: ($screenCfg['inch'] ?? null) ?: ($tournament?->tv_screen_inch ?? 55)),
                (float) ($request->query('m') ?: ($screenCfg['distance'] ?? null) ?: ($tournament?->tv_distance_m ?? 2.0)),
            ),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Only the places of people on the screen right now (the day shown, the
     * courts shown) — never the whole tournament's list.
     */
    private static function tvPlaces(array $matches, array $places, callable $onScreen): array
    {
        if (! $places) {
            return [];
        }
        $today = now('Europe/Vilnius')->toDateString();
        $days = collect($matches)->filter(fn ($m) => ! empty($m['date']) && ! empty($m['time']) && ! empty($m['court']['name']))
            ->pluck('date')->unique()->sort()->values();
        $day = $days->contains($today) ? $today : ($days->first(fn ($d) => $d > $today) ?? $days->last());

        $out = [];
        foreach ($matches as $m) {
            if (($m['date'] ?? null) !== $day || ! $onScreen($m)) {
                continue;
            }
            foreach ($m['participants'] ?? [] as $p) {
                $key = \App\Services\Tournated\TournamentSync::personKey(trim(($p['name'] ?? '').' '.($p['surname'] ?? '')));
                if (isset($places[$key])) {
                    $out[$key] = $places[$key];
                }
            }
        }

        return $out;
    }

    private const ADMIN_COOKIE = 'overlay_admin_token';

    /**
     * Unlinked page (no nav entry) where the organizer sets a per-court delay
     * in minutes while the tournament is live. Guarded by a shared token
     * rather than a login — this is a same-day correction tool, not
     * account-holder data. The token is only ever accepted once via the URL
     * (the bootstrap link); from then on it's read back from an httpOnly
     * cookie so it stops being written into server access logs and browser
     * history on every reload.
     */
    public function delaysForm(Request $request, string $tournamentExternalId)
    {
        $expected = config('services.overlay.admin_token');
        $queryToken = (string) $request->query('token', '');

        if ($expected && $queryToken !== '' && hash_equals($expected, $queryToken)) {
            return redirect()
                ->route('schedule.delays.form', ['tournament' => $tournamentExternalId])
                ->withCookie(cookie(
                    self::ADMIN_COOKIE, $queryToken, 60 * 24 * 30,
                    null, null, $request->secure(), true
                ));
        }

        $this->authorizeAdmin($request);

        $snapshot = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->first();
        abort_if(! $snapshot, 404);

        $payload = $snapshot->payload ?? [];
        $matches = array_values(array_filter($payload['matches'] ?? [], [self::class, 'isUsableMatch']));

        $courts = collect($matches)
            ->pluck('court.name')
            ->filter()
            ->unique()
            ->sort(fn ($a, $b) => $this->courtNumber($a) <=> $this->courtNumber($b))
            ->values();

        return view('pages.schedule-delays', [
            'tournamentId' => $tournamentExternalId,
            'courts'       => $courts,
            'delays'       => $payload['court_delays'] ?? [],
        ]);
    }

    public function delaysSave(Request $request, string $tournamentExternalId)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'delays'   => 'array',
            'delays.*' => 'nullable|integer|min:0|max:240',
        ]);

        $snapshot = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->first();
        abort_if(! $snapshot, 404);

        $payload = $snapshot->payload ?? [];
        $payload['court_delays'] = collect($validated['delays'] ?? [])
            ->filter(fn ($minutes) => (int) $minutes > 0)
            ->map(fn ($minutes) => (int) $minutes)
            ->all();

        $snapshot->update(['payload' => $payload]);

        return redirect()
            ->route('schedule.delays.form', ['tournament' => $tournamentExternalId])
            ->with('saved', true);
    }

    private function authorizeAdmin(Request $request): void
    {
        $expected = config('services.overlay.admin_token');
        $given = (string) ($request->cookie(self::ADMIN_COOKIE) ?: $request->query('token') ?: $request->input('token'));

        abort_if(! $expected || $given === '' || ! hash_equals($expected, $given), 403);
    }

    private function courtNumber(string $courtName): int
    {
        return preg_match('/\d+/', $courtName, $m) ? (int) $m[0] : 999;
    }

    public static function isPlayed(array $match): bool
    {
        return ! empty($match['sets']) || ! empty($match['winner_side']) || ! empty($match['is_walkover']) || ! empty($match['is_bye']);
    }

    /**
     * A handful of low match_ids for this tournament (captured very early,
     * 2026-09-11) came back from Tournated's `view=full` with no time/court
     * and a degenerate placeholder `sets` value (e.g. a lone "0-0" or "1-0"
     * entry) — not real scheduled matches, but they were counting as
     * "played" and showing up with no time/court context. Exclude anything
     * without a real time+court from the public page entirely.
     */
    /**
     * Scheduled matches, plus unscheduled ones whose two sides are known (the
     * page lists those under "Laikas dar nepaskirtas" instead of hiding them).
     * Empty bracket slots and byes stay out.
     */
    /** Groups for the "Lentelės" tab: names and match ids only (no player ids). */
    public static function publicGroups(array $payload): array
    {
        return array_values(array_map(fn (array $g) => [
            'group_id' => $g['group_id'] ?? null,
            'category_id' => $g['category_id'] ?? null,
            'name' => (string) ($g['name'] ?? ''),
            'match_ids' => array_values(array_map('intval', $g['match_ids'] ?? [])),
        ], $payload['groups'] ?? []));
    }

    /**
     * Knockout draws for the bracket view. Matches the schedule hides (byes,
     * rounds whose players aren't known yet) come along as light "slots" so
     * the bracket keeps its shape; real matches are taken from `matches`.
     */
    public static function publicDraws(array $payload): array
    {
        $byId = [];
        foreach ($payload['matches'] ?? [] as $m) {
            if (isset($m['match_id'])) {
                $byId[(int) $m['match_id']] = $m;
            }
        }

        $out = [];
        foreach ($payload['draws'] ?? [] as $d) {
            $ids = array_values(array_map('intval', $d['match_ids'] ?? []));
            if (! $ids) {
                continue;
            }
            $slots = [];
            foreach ($ids as $id) {
                $m = $byId[$id] ?? null;
                if (! $m || self::isUsableMatch($m)) {
                    continue;
                }
                $names = [1 => [], 2 => []];
                foreach ($m['participants'] ?? [] as $p) {
                    $side = (int) ($p['side'] ?? 0);
                    $n = trim(($p['name'] ?? '').' '.($p['surname'] ?? ''));
                    if (isset($names[$side]) && $n !== '') {
                        $names[$side][] = $n;
                    }
                }
                $slots[] = [
                    'match_id' => $id,
                    'round' => $m['round'] ?? null,
                    'bracket_type' => $m['bracket_type'] ?? null,
                    'bye' => ! empty($m['is_bye']) || (bool) $names[1] !== (bool) $names[2],
                    'side1' => $names[1],
                    'side2' => $names[2],
                    'winner_side' => $m['winner_side'] ?? null,
                ];
            }
            $out[] = [
                'draw_id' => $d['draw_id'] ?? null,
                'category_id' => $d['category_id'] ?? null,
                'title' => (string) ($d['title'] ?? 'Tinklelis'),
                'size' => (int) ($d['size'] ?? 0),
                'match_ids' => $ids,
                'slots' => $slots,
            ];
        }

        return $out;
    }

    /**
     * Club-vs-club rosters (see TournamentSync::isTieRow). Older snapshots
     * still have the tie rows inside "matches", so they are read from there too.
     */
    /**
     * League points a club earned for this stage (12/9/7/4 in the Klaipėda club
     * league). Tournated publishes them as a "ranking" standing once the stage
     * is finished; until then nothing is shown.
     */
    public static function publicLeaguePoints(array $payload): array
    {
        foreach ($payload['standings'] ?? [] as $s) {
            if (($s['source'] ?? null) !== 'ranking') {
                continue;
            }
            $rows = array_values(array_filter(array_map(fn (array $e) => [
                'position' => (int) ($e['position'] ?? 0),
                'club' => (string) ($e['team_name'] ?? ''),
                'points' => (int) ($e['points'] ?? 0),
            ], $s['entries'] ?? []), fn (array $r) => $r['club'] !== ''));
            if ($rows && max(array_column($rows, 'points')) > 0) {
                usort($rows, fn ($a, $b) => $a['position'] <=> $b['position']);

                return $rows;
            }
        }

        return [];
    }

    public static function publicTies(array $payload): array
    {
        if (! empty($payload['ties'])) {
            return array_values($payload['ties']);
        }

        return array_values(array_map(fn ($m) => [
            'match_id' => $m['match_id'] ?? null,
            'team1' => $m['team1'] ?? null,
            'team2' => $m['team2'] ?? null,
            'players' => array_values(array_map(fn ($p) => ['side' => $p['side'] ?? null, 'name' => $p['name'] ?? '', 'surname' => $p['surname'] ?? ''], $m['participants'] ?? [])),
        ], array_filter($payload['matches'] ?? [], [TournamentSync::class, 'isTieRow'])));
    }

    public static function isUsableMatch(array $match): bool
    {
        if (! empty($match['is_bye']) || TournamentSync::isTieRow($match)) {
            return false;
        }
        if (! empty($match['time']) && ! empty($match['court']['name'] ?? null)) {
            return true;
        }
        $sides = array_unique(array_column($match['participants'] ?? [], 'side'));

        return in_array(1, $sides, true) && in_array(2, $sides, true);
    }

    /** @return array<string, array<int, array<string, mixed>>> keyed by club title */
    private function groupByClub(array $matches, \Illuminate\Support\Collection $clubs): array
    {
        $out = [];
        foreach ($clubs as $club) {
            $out[$club] = array_values(array_filter(
                $matches,
                fn ($m) => ($m['team1']['title'] ?? null) === $club || ($m['team2']['title'] ?? null) === $club,
            ));
        }

        return $out;
    }

    private function tagDivision(array $match, array $divisionByPair): array
    {
        // The relay (play.padel.lt GraphQL) already sends a real division
        // name (Tournated's own match `name`, e.g. "Moterys B 1") — only
        // fall back to the Excel entry_lists pair-matching guess when that's
        // missing (e.g. an older/different data source, or Tournated left
        // it blank).
        if (! empty($match['division'])) {
            return $match;
        }

        $sig1 = $this->pairSignature($match['participants'] ?? [], 1);
        $sig2 = $this->pairSignature($match['participants'] ?? [], 2);
        $match['division'] = $divisionByPair[$sig1] ?? $divisionByPair[$sig2] ?? null;

        return $match;
    }

    /** Normalised "name1|name2" (alphabetically sorted) signature for one side of a match. */
    private function pairSignature(array $participants, int $side): ?string
    {
        $names = [];
        foreach ($participants as $p) {
            if ((int) ($p['side'] ?? 0) === $side) {
                $full = trim(($p['name'] ?? '') . ' ' . ($p['surname'] ?? ''));
                if ($full !== '') {
                    $names[] = self::normName($full);
                }
            }
        }
        if (empty($names)) {
            return null;
        }
        sort($names);

        return implode('|', $names);
    }

    /**
     * Build a pair-signature => division-display-name map from the manually
     * imported Excel entry list (same source as the draw board). Falls back
     * to an empty map — matches then simply show without a division badge.
     *
     * @return array<string, string>
     */
    private function divisionLookup(string $tournamentExternalId): array
    {
        $entry = EntryList::where('tournament_external_id', $tournamentExternalId)->first();
        if (! $entry || empty($entry->data)) {
            return [];
        }

        $names = $entry->names ?? [];
        $map = [];
        foreach ($entry->data as $norm => $pairs) {
            $label = $names[$norm] ?? $norm;
            foreach ($pairs as $pair) {
                $playerNames = array_map(
                    fn ($p) => self::normName((string) ($p['name'] ?? '')),
                    $pair['players'] ?? [],
                );
                $playerNames = array_values(array_filter($playerNames));
                if (empty($playerNames)) {
                    continue;
                }
                sort($playerNames);
                $map[implode('|', $playerNames)] = $label;
            }
        }

        return $map;
    }

    /** Lowercase, diacritic-stripped, whitespace-collapsed name for fuzzy matching. */
    public static function normName(string $name): string
    {
        $map = [
            'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's',
            'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
            'Ą' => 'a', 'Č' => 'c', 'Ę' => 'e', 'Ė' => 'e', 'Į' => 'i', 'Š' => 's',
            'Ų' => 'u', 'Ū' => 'u', 'Ž' => 'z',
        ];
        $name = strtr($name, $map);
        $name = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));

        return $name;
    }
}
