<?php

namespace App\Http\Controllers;

use App\Models\EntryList;
use App\Models\ScheduleSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public schedule / results / group-tables page for a tournament, fed by the
 * `schedule-push.js` relay (the production host cannot reach api.tournated.com
 * directly, same constraint as the OBS overlays — see docs/overlays.md).
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
                'synced_at'  => now()->toIso8601String(),
            ]],
        );

        return response()->json(['ok' => true]);
    }

    public function show(string $tournamentExternalId)
    {
        $snapshot = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->first();

        abort_if(! $snapshot, 404);

        $payload = $snapshot->payload ?? [];
        $matches = array_values(array_filter($payload['matches'] ?? [], [self::class, 'isUsableMatch']));
        $groups = $payload['groups'] ?? [];
        $standings = $payload['standings'] ?? [];

        $divisionByPair = $this->divisionLookup($tournamentExternalId);
        $matches = array_map(fn (array $m) => $this->tagDivision($m, $divisionByPair), $matches);

        // Sort by date+time, then court, so the grid renders in a stable order.
        usort($matches, function (array $a, array $b) {
            $ka = ($a['date'] ?? '') . ' ' . ($a['time'] ?? '') . ' ' . ($a['court']['court_id'] ?? 0);
            $kb = ($b['date'] ?? '') . ' ' . ($b['time'] ?? '') . ' ' . ($b['court']['court_id'] ?? 0);

            return $ka <=> $kb;
        });

        $courts = collect($matches)->pluck('court.name')->filter()->unique()->values();
        $divisions = collect($matches)->pluck('division')->filter()->unique()->sort()->values();
        $clubs = collect($matches)
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
            'standings'    => $standings,
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
        ]);
    }

    /** JSON the page polls while open, to pick up new results without a full reload. */
    public function data(string $tournamentExternalId): JsonResponse
    {
        $snapshot = ScheduleSnapshot::where('tournament_external_id', $tournamentExternalId)->first();
        abort_if(! $snapshot, 404);

        $payload = $snapshot->payload ?? [];
        $matches = array_values(array_filter($payload['matches'] ?? [], [self::class, 'isUsableMatch']));
        $divisionByPair = $this->divisionLookup($tournamentExternalId);
        $matches = array_map(fn (array $m) => $this->tagDivision($m, $divisionByPair), $matches);

        return response()->json([
            'matches'   => $matches,
            'groups'    => $payload['groups'] ?? [],
            'standings' => $payload['standings'] ?? [],
            'synced_at' => $payload['synced_at'] ?? null,
        ]);
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
    public static function isUsableMatch(array $match): bool
    {
        return ! empty($match['time']) && ! empty($match['court']['name'] ?? null);
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
