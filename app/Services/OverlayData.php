<?php

namespace App\Services;

use App\Models\OverlaySnapshot;
use App\Models\Sponsor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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

    /**
     * The stored bracket segments for a category (each a separate draw — the
     * main tree, or a "dėl N vietos" draw). Normalises the older single-bracket
     * snapshot shape into a one-segment list.
     *
     * @return list<array<string,mixed>>
     */
    public function bracketSegmentsForCategory(string $tournamentId, int $categoryId): array
    {
        $byCat = $this->payload($tournamentId)['brackets_by_category'] ?? [];
        $b = $byCat[(string) $categoryId] ?? null;

        if (! is_array($b)) {
            return [];
        }

        // New shape: { segments: [ {key,label,rounds,third,placements}, ... ] }
        if (isset($b['segments']) && is_array($b['segments'])) {
            return array_values($b['segments']);
        }

        // Legacy shape: a single { rounds, third, placements }.
        if (isset($b['rounds'])) {
            return [[
                'key'        => 'main',
                'label'      => 'Pagrindinis tinklelis',
                'is_main'    => true,
                'rounds'     => $b['rounds'] ?? [],
                'third'      => $b['third'] ?? null,
                'placements' => $b['placements'] ?? [],
            ]];
        }

        return [];
    }

    /**
     * Bracket segments as a key => label map for the admin multi-select.
     *
     * @return array<string,string>
     */
    public function bracketSegments(string $tournamentId, int $categoryId): array
    {
        $out = [];
        foreach ($this->bracketSegmentsForCategory($tournamentId, $categoryId) as $seg) {
            $key = (string) ($seg['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[$key] = $seg['label'] ?? $key;
        }

        return $out;
    }

    /** @return array<int,mixed> */
    public function groups(string $tournamentId, int $categoryId): array
    {
        $byCategory = $this->payload($tournamentId)['groups_by_category'] ?? [];

        return $byCategory[(string) $categoryId] ?? [];
    }

    /**
     * Frozen-pool source: the teams of a category from the snapshot, used to
     * seed a draw window's participant pool.
     *
     * @return array<int,mixed>
     */
    public function participants(string $tournamentId, int $categoryId): array
    {
        $byCat = $this->payload($tournamentId)['participants_by_category'] ?? [];

        return $byCat[(string) $categoryId] ?? [];
    }

    /** @return array<string,mixed> */
    public function categoryStages(string $tournamentId): array
    {
        return $this->payload($tournamentId)['category_stages'] ?? [];
    }

    /**
     * Distinct group segments (Main, 5-8, 9-16, …) of a category, as a
     * value => label map for the admin multi-select. The raw segment string is
     * the value; empty/null is treated as the main draw.
     *
     * @return array<string,string>
     */
    public function segments(string $tournamentId, int $categoryId): array
    {
        $out = [];
        foreach ($this->groups($tournamentId, $categoryId) as $g) {
            $key = (string) ($g['segment'] ?? '');
            $out[$key] = $this->segmentLabel($g['segment'] ?? null);
        }

        return $out;
    }

    /** Human label for a raw segment value (empty/null → main draw). */
    private function segmentLabel(mixed $raw): string
    {
        $s = trim((string) ($raw ?? ''));

        return $s === '' ? 'Main' : $s;
    }

    /**
     * Resolve a groups-window's selected subgroups from the snapshot.
     *
     * @param  array<string,mixed>  $window
     * @return array{groups:list<array<string,mixed>>,subgroup_count:int}
     */
    public function resolveWindow(string $tournamentId, array $window): array
    {
        $groups = [];

        foreach ($window['subgroups'] ?? [] as $sel) {
            $catId = $sel['category_id'] ?? null;
            if (! $catId) {
                continue;
            }

            $raw = $this->groups($tournamentId, (int) $catId);

            $segments = $sel['segments'] ?? [];
            if (! empty($segments)) {
                $segments = array_map('strval', $segments);
                $raw = array_values(array_filter(
                    $raw,
                    fn ($g) => in_array((string) ($g['segment'] ?? ''), $segments, true),
                ));
            }

            $groupId = $sel['group_id'] ?? null;
            if ($groupId) {
                $raw = array_values(array_filter($raw, fn ($g) => $g['id'] == $groupId));
            }

            foreach ($raw as $g) {
                $groups[] = [
                    'id'      => $g['id'],
                    'name'    => $g['name'] ?? '',
                    'segment' => $this->segmentLabel($g['segment'] ?? null),
                    'rows'    => $this->computeStandings($g),
                ];
            }
        }

        return ['groups' => $groups, 'subgroup_count' => count($groups)];
    }

    /** Lowercase + strip Lithuanian/Polish diacritics (stable person key). */
    public function personKey(string $name): string
    {
        $map = [
            'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
            'ł' => 'l', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z', 'ń' => 'n', 'ć' => 'c',
        ];

        return trim(strtr(mb_strtolower($name), $map));
    }

    private function genderFromCategory(?string $cat): string
    {
        $c = mb_strtolower($cat ?? '');

        return (str_contains($c, 'moter') || str_contains($c, 'women') || str_contains($c, 'female')) ? 'M' : 'V';
    }

    /** Distinct individual people across a tournament's participant pairs. @return list<string> */
    public function participantsPeople(string $tournamentId): array
    {
        $out = [];
        foreach ($this->payload($tournamentId)['participants_by_category'] ?? [] as $teams) {
            foreach ($teams as $t) {
                foreach (explode(' / ', (string) ($t['name'] ?? '')) as $person) {
                    $person = trim($person);
                    if ($person !== '') {
                        $out[$this->personKey($person)] = $person;
                    }
                }
            }
        }

        return array_values($out);
    }

    /** @return array{name:string,photo:string,is_stock:bool} */
    public function photoFor(string $tournamentId, string $name, string $fallbackGender): array
    {
        $row = \App\Models\PlayerPhoto::where('tournament_external_id', $tournamentId)
            ->where('person_key', $this->personKey($name))
            ->first();

        if ($row && $row->photo) {
            return ['name' => $name, 'photo' => Storage::url($row->photo), 'is_stock' => false];
        }

        $gender = $row->gender ?? $fallbackGender;
        $file = $gender === 'M' ? 'player-female.svg' : 'player-male.svg';

        return ['name' => $name, 'photo' => asset('img/h2h/' . $file), 'is_stock' => true];
    }

    /**
     * Resolve the chosen head-to-head match into two photo-bearing sides + centre.
     *
     * @param  array<string,mixed>  $window
     * @return array<string,mixed>
     */
    public function resolveH2h(string $tournamentId, $matchId, array $window): array
    {
        $matches = $this->payload($tournamentId)['matches'] ?? [];
        $m = collect($matches)->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $matchId);

        if (! $m) {
            return ['found' => false];
        }

        $gender = $this->genderFromCategory($m['category'] ?? null);
        $side = fn ($players) => array_map(fn ($n) => $this->photoFor($tournamentId, $n, $gender), $players ?: []);

        return [
            'found'       => true,
            'team1'       => $side($m['team1'] ?? []),
            'team2'       => $side($m['team2'] ?? []),
            'category'    => $m['category'] ?? null,
            'center'      => [
                'time'        => $m['time'] ?? null,
                'date'        => $m['date'] ?? null,
                'score'       => $m['score'] ?? null,
                'court'       => $m['court'] ?? null,
                'round'       => $m['round'] ?? null,
                'in_progress' => ! empty($m['in_progress']),
            ],
            'show'        => $window['h2h_center'] ?? ['time', 'score', 'court'],
            'custom_text' => $window['h2h_text'] ?? 'VS',
            'animate'     => (bool) ($window['h2h_animate'] ?? true),
        ];
    }

    /**
     * Assemble the draw-window payload from the live runtime state + config.
     *
     * @param  array<string,mixed>  $window
     * @param  array<string,mixed>  $drawState
     * @return array<string,mixed>
     */
    public function resolveDraw(array $window, array $drawState): array
    {
        $engine = app(\App\Services\DrawEngine::class);
        $layout = $engine->layout($window);
        $teams = collect($drawState['teams'] ?? [])->keyBy('id');

        $nameOf = fn ($id) => $id === null ? null
            : ['id' => $id, 'name' => $id === \App\Services\DrawEngine::BYE ? 'BYE' : ($teams[$id]['name'] ?? ('#' . $id))];

        $slots = [];
        foreach (($drawState['slots'] ?? []) as $key => $tid) {
            $slots[$key] = $nameOf($tid);
        }

        $placedIds = array_values(array_filter($drawState['slots'] ?? [], fn ($t) => $t !== null));
        $pool = collect($drawState['teams'] ?? [])
            ->reject(fn ($t) => in_array($t['id'], $placedIds, true))
            ->map(fn ($t) => ['id' => $t['id'], 'name' => $t['name'] ?? ('#' . $t['id'])])
            ->values()->all();

        $current = $drawState['current'] ?? null;
        if ($current) {
            $tid = $current['team_id'] ?? null;
            $current = [
                'team_id' => $tid,
                'name' => $tid === \App\Services\DrawEngine::BYE ? 'BYE' : ($teams[$tid]['name'] ?? ''),
                'slot' => $current['slot'],
            ];
        }

        $board = $layout['format'] === 'bracket' ? $layout['pairs'] : $layout['groups'];

        return [
            'format' => $layout['format'],
            'board' => $board,
            'slots' => $slots,
            'pool' => $pool,
            'current' => $current,
            'status' => $drawState['status'] ?? 'idle',
            'active_pot' => $drawState['active_pot'] ?? 1,
            'camera_corner' => $window['camera_corner'] ?? 'bottom-right',
            'show_tournament' => (bool) ($window['show_tournament'] ?? true),
            'sponsors' => $this->resolveSponsors($window),
            'rotate_seconds' => (int) ($window['rotate_seconds'] ?? 8),
        ];
    }

    /**
     * Resolve a schedule (order-of-play) window from the snapshot matches.
     *
     * @param  array<string,mixed>  $window
     * @return array<string,mixed>
     */
    public function resolveSchedule(string $tournamentId, array $window): array
    {
        $variant = $window['schedule_variant'] ?? 'by_court';
        $matches = $this->payload($tournamentId)['matches'] ?? [];

        if (! empty($window['date'])) {
            $date = substr((string) $window['date'], 0, 10);
            $matches = array_filter($matches, fn ($m) => ($m['date'] ?? null) === $date);
        }
        if (! empty($window['category_ids'])) {
            $cats = array_map('intval', $window['category_ids']);
            $matches = array_filter($matches, fn ($m) => in_array((int) ($m['category_id'] ?? 0), $cats, true));
        }
        if (! empty($window['courts'])) {
            $courts = array_map('intval', $window['courts']);
            $matches = array_filter($matches, fn ($m) => in_array((int) ($m['court_id'] ?? 0), $courts, true));
        }
        $matches = array_values($matches);

        $byTime = fn ($a, $b) => strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? ''));

        if (in_array($variant, ['now', 'next', 'results'], true)) {
            if ($variant === 'now') {
                $items = array_values(array_filter($matches, fn ($m) => ! empty($m['in_progress'])));
                usort($items, $byTime);
            } elseif ($variant === 'next') {
                // Whatever is on court now first, then the upcoming (pending) matches.
                $live = array_values(array_filter($matches, fn ($m) => ! empty($m['in_progress'])));
                $upcoming = array_values(array_filter(
                    $matches,
                    fn ($m) => ($m['status'] ?? null) === 'pending' && empty($m['in_progress']),
                ));
                usort($live, $byTime);
                usort($upcoming, $byTime);
                $items = array_merge($live, $upcoming);
            } else { // results — finished matches (have a score, not live), newest first
                $items = array_values(array_filter(
                    $matches,
                    fn ($m) => ! empty($m['score']) && empty($m['in_progress']),
                ));
                // Sort by the actual finish time when known, else fall back to
                // the scheduled date+time. Newest first.
                $finishKey = function ($m) {
                    if (! empty($m['finished_at'])) {
                        return (string) $m['finished_at'];
                    }
                    $date = $m['date'] ?? '';

                    return $date !== '' ? $date . 'T' . ($m['time'] ?? '') : '';
                };
                usort($items, fn ($a, $b) => strcmp($finishKey($b), $finishKey($a)));
            }

            $limit = (int) ($window['limit'] ?? 0);
            if ($limit > 0) {
                $items = array_slice($items, 0, $limit);
            }

            return ['variant' => $variant, 'items' => $items];
        }

        usort($matches, $byTime);
        $key = $variant === 'by_time' ? 'time' : 'court';
        $groups = [];
        foreach ($matches as $m) {
            $groups[(string) ($m[$key] ?? '—')][] = $m;
        }
        if ($variant === 'by_court') {
            ksort($groups);
        }

        return ['variant' => $variant, 'groups' => array_map(
            fn ($heading, $ms) => ['heading' => $heading, 'matches' => $ms],
            array_keys($groups),
            array_values($groups),
        )];
    }

    /**
     * Distinct courts (id => name) from the snapshot matches, for the admin select.
     *
     * @return array<int,string>
     */
    public function courts(string $tournamentId): array
    {
        $out = [];
        foreach ($this->payload($tournamentId)['matches'] ?? [] as $m) {
            $id = $m['court_id'] ?? null;
            if ($id) {
                $out[(int) $id] = $m['court'] ?? ('#' . $id);
            }
        }
        asort($out);

        return $out;
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
                'points' => $w,
                'losses' => $allDone ? ($n - 1 - $w) : null,
                'played' => $allDone ? ($n - 1) : $w,
            ];
        }, $entries);

        usort($rows, fn ($a, $b) => ($a['place'] ?? 99) <=> ($b['place'] ?? 99));

        return array_values($rows);
    }

    /**
     * Build the ordered sponsor items for a sponsors window: selected active
     * sponsors first (in the chosen order), then uploaded images.
     *
     * @param  array<string,mixed>  $window
     * @return list<array{logo:string,name:?string,url:?string}>
     */
    public function resolveSponsors(array $window): array
    {
        $items = [];

        $ids = $window['sponsor_ids'] ?? [];
        if (! empty($ids)) {
            $sponsors = Sponsor::whereIn('id', $ids)->where('is_active', true)->get()->keyBy('id');
            foreach ($ids as $id) {
                $s = $sponsors->get($id);
                if ($s) {
                    $items[] = ['logo' => Storage::url($s->logo), 'name' => $s->name, 'url' => $s->url];
                }
            }
        }

        foreach ($window['images'] ?? [] as $path) {
            $items[] = ['logo' => Storage::url($path), 'name' => null, 'url' => null];
        }

        return $items;
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
