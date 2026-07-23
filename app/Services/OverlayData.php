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
        $cats = $this->payload($tournamentId)['categories'] ?? [];

        // Add categories that exist only in a manually imported Excel entry list
        // (so the draw can pick them before Tournated has loaded categories).
        // They get a stable synthetic id and disappear once the real category
        // (same name) arrives from the scraper.
        $entry = \App\Models\EntryList::where('tournament_external_id', $tournamentId)->first();
        if ($entry && ! empty($entry->data)) {
            $have = [];
            foreach ($cats as $c) {
                $have[\App\Models\EntryList::normCategory((string) ($c['category']['name'] ?? ''))] = true;
            }
            $names = $entry->names ?? [];
            foreach (array_keys($entry->data) as $norm) {
                if (! isset($have[$norm])) {
                    $cats[] = [
                        'id'       => 900000000 + (crc32($norm) % 90000000), // sintetinis, stabilus
                        'category' => ['id' => null, 'name' => $names[$norm] ?? $norm],
                        'imported' => true,
                    ];
                }
            }
        }

        return $cats;
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
        // Manually imported Excel entry list wins (for the draw before matches
        // exist). It is keyed by normalised category name.
        $manual = \App\Models\EntryList::where('tournament_external_id', $tournamentId)->value('data');
        if (! empty($manual)) {
            foreach ($this->categories($tournamentId) as $c) {
                if ((string) ($c['id'] ?? '') === (string) $categoryId) {
                    $key = \App\Models\EntryList::normCategory((string) ($c['category']['name'] ?? ''));
                    if (! empty($manual[$key])) {
                        return $manual[$key];
                    }
                }
            }
        }

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

    /** Raw snapshot matches (fixtures) for a tournament. @return array<int,mixed> */
    public function matches(string $tournamentId): array
    {
        return $this->payload($tournamentId)['matches'] ?? [];
    }

    /** "Jevgenij Grigorenko" → "J. Grigorenko" (first-name initial + surname). */
    public function abbrevName(string $full): string
    {
        $full = trim(preg_replace('/\s+/', ' ', $full));
        if ($full === '') {
            return '';
        }
        $parts = explode(' ', $full);
        if (count($parts) === 1) {
            return $parts[0];
        }
        $first = array_shift($parts);

        return mb_strtoupper(mb_substr($first, 0, 1)) . '. ' . implode(' ', $parts);
    }

    /** @return array<string,mixed> */
    public function scoreConfig(array $window): array
    {
        return app(\App\Services\ScoreEngine::class)->config($window);
    }

    /**
     * Build the scoreboard card payload from the live score state + match context.
     *
     * @param  array<string,mixed>  $window
     * @param  array<string,mixed>  $state
     * @param  array<string,mixed>  $match   category/court/round context
     * @param  array<string,mixed>  $config
     * @return array<string,mixed>
     */
    public function resolveScore(array $window, array $state, array $match, array $config): array
    {
        if (empty($state['teams'])) {
            return ['found' => false];
        }

        $labels = ['0', '15', '30', '40'];
        $mode = $config['deuce_mode'] ?? 'star';
        $bothAt40 = ($state['points'][0] ?? 0) >= 3 && ($state['points'][1] ?? 0) >= 3;

        $pointFor = function (int $t) use ($state, $labels, $mode, $bothAt40) {
            if (! empty($state['tiebreak'])) {
                return (string) ($state['tb'][$t] ?? 0);
            }
            if (($state['star_stage'] ?? 0) === 'star' || ($mode === 'golden' && $bothAt40 && ($state['adv'] ?? null) === null)) {
                return '★';
            }
            if (($state['adv'] ?? null) === $t) {
                $stage = $state['star_stage'] ?? 0;

                return $stage === 'adv1' ? '1AD' : ($stage === 'adv2' ? '2AD' : 'AD');
            }
            if (($state['adv'] ?? null) === (1 - $t)) {
                return '40';
            }

            return $labels[min((int) ($state['points'][$t] ?? 0), 3)];
        };

        $team = fn (int $t) => [
            'name'    => implode(' / ', array_map(fn ($n) => $this->abbrevName((string) $n), $state['teams'][$t] ?? [])),
            'sets'    => array_map(fn ($s) => $s[$t], $state['sets'] ?? []),
            'games'   => (int) ($state['games'][$t] ?? 0),
            'point'   => $pointFor($t),
            'serving' => (int) ($state['server_team'] ?? 0) === $t,
            'winner'  => ($state['winner'] ?? null) === $t,
        ];

        return [
            'found'    => true,
            'teams'    => [$team(0), $team(1)],
            'level'    => ($window['show_level'] ?? true) ? ($match['category'] ?? null) : null,
            'court'    => $match['court'] ?? null,
            'round'    => $match['round'] ?? null,
            'tiebreak' => ! empty($state['tiebreak']),
            'status'   => $state['status'] ?? 'playing',
            'position' => $window['score_position'] ?? 'top-left',
            'width'    => (int) ($window['score_width'] ?? 520),
        ];
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

    /** Map a country name/code to an ISO-3166 alpha-2 code (for a flag), or null. */
    private function countryCode(?string $country): ?string
    {
        $c = $this->personKey((string) ($country ?? ''));
        if ($c === '') {
            return null;
        }
        if (preg_match('/^[a-z]{2}$/', $c)) {
            return $c;
        }

        $map = [
            'lietuva' => 'lt', 'lithuania' => 'lt', 'ltu' => 'lt',
            'latvija' => 'lv', 'latvia' => 'lv', 'lva' => 'lv',
            'estija' => 'ee', 'estonia' => 'ee', 'est' => 'ee',
            'lenkija' => 'pl', 'poland' => 'pl', 'polska' => 'pl', 'pol' => 'pl',
            'ispanija' => 'es', 'spain' => 'es', 'espana' => 'es', 'esp' => 'es',
            'italija' => 'it', 'italy' => 'it', 'ita' => 'it',
            'prancuzija' => 'fr', 'france' => 'fr', 'fra' => 'fr',
            'vokietija' => 'de', 'germany' => 'de', 'deu' => 'de',
            'svedija' => 'se', 'sweden' => 'se', 'swe' => 'se',
            'suomija' => 'fi', 'finland' => 'fi', 'fin' => 'fi',
            'norvegija' => 'no', 'norway' => 'no', 'nor' => 'no',
            'danija' => 'dk', 'denmark' => 'dk', 'dnk' => 'dk',
            'jungtine karalyste' => 'gb', 'didzioji britanija' => 'gb', 'uk' => 'gb', 'gbr' => 'gb', 'england' => 'gb',
            'airija' => 'ie', 'ireland' => 'ie', 'irl' => 'ie',
            'portugalija' => 'pt', 'portugal' => 'pt', 'prt' => 'pt',
            'ukraina' => 'ua', 'ukraine' => 'ua', 'ukr' => 'ua',
            'olandija' => 'nl', 'nyderlandai' => 'nl', 'netherlands' => 'nl', 'nld' => 'nl',
            'belgija' => 'be', 'belgium' => 'be', 'bel' => 'be',
            'sveicarija' => 'ch', 'switzerland' => 'ch', 'che' => 'ch',
            'austrija' => 'at', 'austria' => 'at', 'aut' => 'at',
            'cekija' => 'cz', 'czech' => 'cz', 'cze' => 'cz',
            'slovakija' => 'sk', 'slovakia' => 'sk', 'svk' => 'sk',
            'vengrija' => 'hu', 'hungary' => 'hu', 'hun' => 'hu',
            'rumunija' => 'ro', 'romania' => 'ro', 'rou' => 'ro',
            'graikija' => 'gr', 'greece' => 'gr', 'grc' => 'gr',
            'argentina' => 'ar', 'arg' => 'ar',
            'brazilija' => 'br', 'brazil' => 'br', 'bra' => 'br',
            'jav' => 'us', 'usa' => 'us',
        ];

        return $map[$c] ?? null;
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

    /**
     * Neapdorotas žaidėjų sąrašas iš snapshot'o: [{id, name, nation}, ...].
     *
     * @return list<array<string,mixed>>
     */
    public function peopleList(string $tournamentId): array
    {
        return $this->payload($tournamentId)['people'] ?? [];
    }

    /**
     * Žaidėjai iš Tournated: personKey => ['id' => Tournated user id, 'nation' => kodas].
     *
     * @return array<string,array{id:?int,nation:?string}>
     */
    public function peopleByKey(string $tournamentId): array
    {
        $out = [];
        foreach ($this->payload($tournamentId)['people'] ?? [] as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[$this->personKey($name)] = [
                'id'     => isset($p['id']) && $p['id'] !== null ? (int) $p['id'] : null,
                'nation' => $p['nation'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Globali žaidėjo paieška: pagal Tournated ID (jei yra), kitaip pagal vardą.
     *
     * @return array<string,mixed>
     */
    public function photoFor(?int $userId, string $name, string $fallbackGender): array
    {
        $row = $userId
            ? \App\Models\PlayerPhoto::where('tournated_user_id', $userId)->first()
            : \App\Models\PlayerPhoto::where('person_key', $this->personKey($name))->first();

        $code = $this->countryCode($row->country ?? null);
        $info = [
            'rating_type'   => $row->rating_type ?? null,
            'rating_points' => $row->rating_points ?? null,
            'country'       => $row->country ?? null,
            'city'          => $row->city ?? null,
            'flag'          => $code ? "https://flagcdn.com/32x24/{$code}.png" : null,
        ];

        if ($row && $row->photo) {
            return array_merge(['name' => $name, 'photo' => Storage::url($row->photo), 'is_stock' => false], $info);
        }

        // No own photo → an admin-uploaded stock, else the bundled silhouette.
        $gender = $row->gender ?? $fallbackGender;
        $isFemale = $gender === 'M';
        $custom = \App\Models\Setting::get($isFemale ? 'h2h_stock_female' : 'h2h_stock_male');
        $photo = $custom
            ? Storage::url($custom)
            : asset('img/h2h/' . ($isFemale ? 'player-female.svg' : 'player-male.svg'));

        return array_merge(['name' => $name, 'photo' => $photo, 'is_stock' => true], $info);
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
        // Prefer players1/players2 (carry Tournated user id); fall back to names.
        $side = function ($players, $names) use ($gender) {
            if (is_array($players) && count($players)) {
                return array_map(fn ($p) => $this->photoFor(
                    isset($p['id']) && $p['id'] !== null ? (int) $p['id'] : null,
                    (string) ($p['name'] ?? ''),
                    $gender,
                ), $players);
            }

            return array_map(fn ($n) => $this->photoFor(null, (string) $n, $gender), $names ?: []);
        };

        return [
            'found'       => true,
            'team1'       => $side($m['players1'] ?? null, $m['team1'] ?? []),
            'team2'       => $side($m['players2'] ?? null, $m['team2'] ?? []),
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
            'sponsors'    => ! empty($window['h2h_show_sponsors']) ? $this->resolveSponsors($window) : [],
            'rotate_seconds' => (int) ($window['rotate_seconds'] ?? 5),
            'sponsor'     => [
                'logo' => ! empty($window['h2h_sponsor_logo']) ? Storage::url($window['h2h_sponsor_logo']) : null,
                'text' => $window['h2h_sponsor_text'] ?? null,
            ],
            'layout'      => [
                'size'    => $window['h2h_size'] ?? 96,      // photo height (vh)
                'edge'    => $window['h2h_edge'] ?? 0,       // distance from screen edges (vw)
                'gap'     => $window['h2h_gap'] ?? 0,        // gap between the two teams (vw)
                'overlap' => $window['h2h_overlap'] ?? 24,   // teammate overlap (vw)
            ],
            'bg'          => $this->h2hBackground($window),
        ];
    }

    /**
     * Animated background config for the H2H window: none / gradient (colour
     * mixing) / image (gradient + a multiplied, floating image).
     *
     * @param  array<string,mixed>  $window
     * @return array<string,mixed>
     */
    private function h2hBackground(array $window): array
    {
        $mode = $window['h2h_bg_mode'] ?? 'none';
        $intensity = $window['h2h_bg_intensity'] ?? 'subtle';

        return [
            'mode'      => in_array($mode, ['none', 'gradient', 'image'], true) ? $mode : 'none',
            'intensity' => in_array($intensity, ['subtle', 'medium', 'bold'], true) ? $intensity : 'subtle',
            'image'     => ! empty($window['h2h_bg_image']) ? Storage::url($window['h2h_bg_image']) : null,
            'count'     => isset($window['h2h_bg_count']) && $window['h2h_bg_count'] !== '' ? (int) $window['h2h_bg_count'] : null,
            'speed'     => isset($window['h2h_bg_speed']) && $window['h2h_bg_speed'] !== '' ? (float) $window['h2h_bg_speed'] : null,
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

        // Each team → its members (name + country flag), for a two-line layout.
        $playersOf = function ($team) {
            if (! empty($team['players']) && is_array($team['players'])) {
                return array_values(array_map(function ($p) {
                    $code = $this->countryCode($p['country'] ?? null);

                    return ['name' => (string) ($p['name'] ?? ''), 'flag' => $code ? "https://flagcdn.com/32x24/{$code}.png" : null];
                }, $team['players']));
            }
            // Fallback: split the joined "P1 / P2" name (no flags).
            $parts = array_values(array_filter(array_map('trim', explode('/', (string) ($team['name'] ?? '')))));

            return array_map(fn ($n) => ['name' => $n, 'flag' => null], $parts);
        };

        $teamOf = function ($id) use ($teams, $playersOf) {
            if ($id === null) {
                return null;
            }
            if ($id === \App\Services\DrawEngine::BYE) {
                return ['id' => $id, 'name' => 'BYE', 'players' => [['name' => 'BYE', 'flag' => null]]];
            }
            $t = $teams[$id] ?? [];

            return ['id' => $id, 'name' => $t['name'] ?? ('#' . $id), 'players' => $playersOf($t)];
        };

        $slots = [];
        foreach (($drawState['slots'] ?? []) as $key => $tid) {
            $slots[$key] = $teamOf($tid);
        }

        $placedIds = array_values(array_filter($drawState['slots'] ?? [], fn ($t) => $t !== null));
        $pool = collect($drawState['teams'] ?? [])
            ->reject(fn ($t) => in_array($t['id'], $placedIds, true))
            ->map(fn ($t) => ['id' => $t['id'], 'name' => $t['name'] ?? ('#' . $t['id']), 'players' => $playersOf($t)])
            ->values()->all();

        $current = $drawState['current'] ?? null;
        if ($current) {
            $tid = $current['team_id'] ?? null;
            $current = array_merge($teamOf($tid) ?? [], ['team_id' => $tid, 'slot' => $current['slot']]);
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
            'main_sponsor' => ! empty($window['draw_sponsor_logo']) ? Storage::url($window['draw_sponsor_logo']) : null,
            'main_sponsor_position' => $window['draw_sponsor_position'] ?? 'top-right',
            'main_sponsor_size' => $window['draw_sponsor_size'] ?? 'm',
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

        // Reusable named galleries — pull their images in the chosen order.
        $galleryIds = $window['gallery_ids'] ?? [];
        if (! empty($galleryIds)) {
            $galleries = \App\Models\Gallery::whereIn('id', $galleryIds)->get()->keyBy('id');
            foreach ($galleryIds as $gid) {
                $g = $galleries->get($gid);
                if ($g) {
                    foreach ($g->imagePaths() as $path) {
                        $items[] = ['logo' => Storage::url($path), 'name' => null, 'url' => null];
                    }
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
