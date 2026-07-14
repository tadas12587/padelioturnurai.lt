<?php

namespace App\Http\Controllers;

use App\Models\Overlay;
use App\Models\OverlaySnapshot;
use App\Models\TournamentScore;
use App\Services\OverlayData;
use App\Services\ScoreEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverlayController extends Controller
{
    public function show(Overlay $overlay)
    {
        return view('overlays.window', ['overlay' => $overlay]);
    }

    /** Simplified, login-free control panel for an OBS browser dock. */
    public function control(Overlay $overlay)
    {
        return view('overlays.control', ['overlay' => $overlay]);
    }

    /** Standalone, login-free mobile scoreboard control (token-authorised). */
    public function scoreControl(Overlay $overlay)
    {
        return view('overlays.score-control', ['overlay' => $overlay]);
    }

    /** Scoreboard actions from the mobile control (token-authorised, CSRF-exempt). */
    public function scoreAction(Overlay $overlay, Request $request, OverlayData $data, ScoreEngine $engine): JsonResponse
    {
        $windows = $overlay->windows ?? [];
        $wi = null;
        foreach ($windows as $i => $w) {
            if (($w['type'] ?? null) === 'score') {
                $wi = $i;
                break;
            }
        }
        if ($wi === null) {
            return response()->json(['error' => 'no_score_window'], 404);
        }
        $window = $windows[$wi];

        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $tid = (string) $overlay->tournament_external_id;
        $score = TournamentScore::stateFor($tid);       // shared across the tournament's overlays
        $matchId = TournamentScore::matchFor($tid);
        $config = $engine->config($window);
        $findMatch = fn ($id) => collect($data->matches($tid))->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $id);

        switch ($request->input('action', 'state')) {
            case 'point':
                if (! empty($score)) {
                    $score = $engine->point($config, $score, (int) $request->input('team'));
                }
                break;
            case 'game':
                if (! empty($score)) {
                    $score = $engine->game($config, $score, (int) $request->input('team'));
                }
                break;
            case 'undo':
                if (! empty($score)) {
                    $score = $engine->undo($config, $score);
                }
                break;
            case 'serve':
                if (! empty($score)) {
                    $score = $engine->setServer($score, (int) $request->input('team'));
                }
                break;
            case 'reset':
                if (! empty($score)) {
                    $score = $engine->reset($config, $score);
                }
                break;
            case 'select':
                // Only load the (shared) score for the chosen match. Do NOT change
                // which window is shown — use 'play' for the standalone score window,
                // or the Akistata centre toggle to show it inside Head-to-Head.
                $m = $findMatch($request->input('match_id'));
                if ($m) {
                    $score = $engine->init($config, [$m['team1'] ?? [], $m['team2'] ?? []]);
                    $matchId = $request->input('match_id');
                }
                break;
            case 'rules':
                foreach (['score_games_per_set', 'score_tiebreak_at', 'score_sets_to_win', 'score_tiebreak', 'score_tiebreak_to', 'score_super_tb', 'score_super_tb_to', 'score_deuce_mode'] as $k) {
                    if ($request->has($k)) {
                        $window[$k] = $request->input($k);
                    }
                }
                $windows[$wi] = $window;
                $overlay->windows = $windows;
                $config = $engine->config($window);
                break;
            case 'play':
                $state = Overlay::showWindow($state, $window['id']);
                break;
            case 'stop':
                $state = Overlay::hideWindow($state, $window['id']);
                break;
            case 'show_window':
                if ($wid = $request->input('window_id')) {
                    $state = Overlay::showWindow($state, (string) $wid);
                }
                break;
            case 'hide_window':
                if ($wid = $request->input('window_id')) {
                    $state = Overlay::hideWindow($state, (string) $wid);
                }
                break;
            case 'center_score':
                // Show/hide the live score inside the Head-to-Head centre.
                $state['h2h_show_score'] = ! ($state['h2h_show_score'] ?? false);
                break;
        }

        TournamentScore::put($tid, $score, $matchId);   // shared: visible in every overlay of this tournament
        unset($state['score'], $state['score_match_id']); // score is now tournament-scoped, not per-overlay
        $overlay->state = $state;
        $overlay->save();

        $m = $findMatch($matchId) ?? [];

        return response()->json([
            'ok'       => true,
            'card'     => $data->resolveScore($window, $score, $m, $config),
            'status'   => $score['status'] ?? 'playing',
            'tiebreak' => ! empty($score['tiebreak']),
            'super_tiebreak' => ! empty($score['super_tiebreak']),
            'match_id' => $matchId,
            'active'   => Overlay::isShown($state, (string) $window['id']),
            'windows_list' => array_map(fn ($w) => [
                'id'    => $w['id'] ?? null,
                'name'  => $w['name'] ?? ($w['type'] ?? 'Langas'),
                'type'  => $w['type'] ?? 'groups',
                'shown' => Overlay::isShown($state, (string) ($w['id'] ?? '')),
            ], $windows),
            'has_h2h'      => collect($windows)->contains(fn ($w) => ($w['type'] ?? null) === 'h2h'),
            'center_score' => (bool) ($state['h2h_show_score'] ?? false),
            'rules'    => [
                'games_per_set' => $config['games_per_set'], 'tiebreak_at' => $config['tiebreak_at'],
                'sets_to_win' => $config['sets_to_win'], 'tiebreak' => $config['tiebreak'], 'tiebreak_to' => $config['tiebreak_to'],
                'super_tb' => $config['super_tb'], 'super_tb_to' => $config['super_tb_to'], 'deuce_mode' => $config['deuce_mode'],
            ],
            'fixtures' => array_map(fn ($x) => [
                'id' => $x['id'] ?? null, 't1' => implode(' / ', $x['team1'] ?? []), 't2' => implode(' / ', $x['team2'] ?? []),
                'time' => $x['time'] ?? null, 'court' => $x['court'] ?? null, 'cat' => $x['category'] ?? null,
            ], $data->matches($tid)),
        ]);
    }

    /**
     * Play/stop a window from the OBS dock (token-authorised, CSRF-exempt).
     * This dock is an exclusive switch: playing a window makes it the only one
     * shown (old off, new on). Pass add=1 to instead add without replacing.
     */
    public function controlAction(Overlay $overlay, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action'    => 'required|in:play,stop',
            'window_id' => 'nullable|string',
            'add'       => 'nullable|boolean',
        ]);

        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $wid = $validated['window_id'] ?? null;
        if ($validated['action'] === 'play') {
            if ($wid) {
                $state = ! empty($validated['add'])
                    ? Overlay::showWindow($state, $wid)      // add to the current set
                    : Overlay::withActive($state, [$wid]);   // exclusive switch (default)
            }
        } else {
            $state = $wid ? Overlay::hideWindow($state, $wid) : Overlay::hideAll($state);
        }
        $overlay->state = $state;
        $overlay->save();

        return response()->json(['active_window_ids' => Overlay::activeIds($state)]);
    }

    public function data(Overlay $overlay, OverlayData $data): JsonResponse
    {
        $config = array_merge(Overlay::defaultConfig(), $overlay->config ?? []);
        $state  = array_merge(Overlay::defaultState(), $overlay->state ?? []);

        $logo = ! empty($config['logo']) ? \Illuminate\Support\Facades\Storage::url($config['logo']) : null;

        $tid = (string) $overlay->tournament_external_id;
        $tournamentTitle = $tid !== '' ? ($data->tournament($tid)['title'] ?? null) : null;

        $base = [
            'title'            => $config['title'],
            'tournament_title' => $tournamentTitle,
            'colors'      => $config['colors'],
            'accent'      => $config['colors']['accent'] ?? '#C9A84C',
            'logo'        => $logo,
            'position'    => $config['position'],
            'columns'     => $config['visible_columns'],
            'next_match'  => $state['next_match'],
        ];

        // Resolve every active window — several can be shown at once.
        $windows = [];
        foreach (Overlay::activeIds($state) as $activeId) {
            $window = collect($overlay->windows ?? [])->firstWhere('id', $activeId);
            if (! $window) {
                continue;
            }
            $windows[] = $this->resolveWindowPayload($overlay, $window, $state, $data, $tid, $base);
        }

        $payload = array_merge($base, [
            'visible'     => count($windows) > 0,
            'window_id'   => null,
            'window_type' => null,
            'stale'       => false,
            'windows'     => $windows,
        ]);

        // Backward-compat: also expose the first window at the top level so
        // anything reading the old flat shape keeps working.
        if (! empty($windows)) {
            $payload = array_merge($payload, $windows[0]);
            $payload['windows'] = $windows;
            $payload['visible'] = true;
        }

        return response()->json($payload);
    }

    /**
     * Resolve a single active window into a self-contained payload (base fields
     * + window_type + its type-specific data). @return array<string,mixed>
     */
    private function resolveWindowPayload(Overlay $overlay, array $window, array $state, OverlayData $data, string $tid, array $base): array
    {
        $activeId = $window['id'] ?? null;
        $type = $window['type'] ?? 'groups';

        $payload = array_merge($base, [
            'visible'     => true,
            'window_id'   => $activeId,
            'window_type' => $type,
            'stale'       => false,
            'scrim'       => [
                'enabled' => (bool) ($window['scrim_enabled'] ?? false),
                'opacity' => (int) ($window['scrim_opacity'] ?? 55),
            ],
        ]);

        if ($type === 'schedule') {
            $payload['schedule_variant'] = $window['schedule_variant'] ?? 'by_court';
            $payload['schedule'] = $data->resolveSchedule($tid, $window);
        } elseif ($type === 'bracket') {
            $segments = $data->bracketSegmentsForCategory($tid, (int) ($window['category_id'] ?? 0));

            $selected = array_map('strval', $window['segments'] ?? []);
            if (! empty($selected)) {
                $segments = array_values(array_filter(
                    $segments,
                    fn ($s) => in_array((string) ($s['key'] ?? ''), $selected, true),
                ));
            }

            $payload['bracket'] = ['segments' => $segments];
        } elseif ($type === 'draw') {
            $drawState = $state['draws'][$activeId] ?? [];
            if (empty($drawState)) {
                $drawState = app(\App\Services\DrawEngine::class)->init($window, []);
            }
            $payload['draw'] = $data->resolveDraw($window, $drawState);
            $catName = null;
            foreach ($data->categories($tid) as $c) {
                if ((string) ($c['id'] ?? '') === (string) ($window['category_id'] ?? '')) {
                    $catName = $c['category']['name'] ?? null;
                }
            }
            $payload['draw']['category'] = $catName;
        } elseif ($type === 'score') {
            $scoreState = TournamentScore::stateFor($tid);   // shared per tournament
            $matchId = TournamentScore::matchFor($tid);
            $m = collect($data->matches($tid))
                ->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $matchId) ?? [];
            $payload['score'] = $data->resolveScore($window, $scoreState, $m, $data->scoreConfig($window));
        } elseif ($type === 'h2h') {
            $payload['h2h'] = $data->resolveH2h($tid, $state['h2h_match_id'] ?? null, $window);
            // Optional: show the live (tournament-shared) score in the centre.
            $sharedScore = TournamentScore::stateFor($tid);
            if (! empty($state['h2h_show_score']) && ! empty($sharedScore['teams'])) {
                $scoreWindow = collect($overlay->windows ?? [])->firstWhere('type', 'score') ?? [];
                $hm = collect($data->matches($tid))
                    ->first(fn ($x) => (string) ($x['id'] ?? '') === (string) ($state['h2h_match_id'] ?? '')) ?? [];
                $sc = $data->resolveScore($scoreWindow, $sharedScore, $hm, $data->scoreConfig($scoreWindow));
                if (! empty($sc['found'])) {
                    $payload['h2h']['live_score'] = $sc;
                }
            }
        } elseif ($type === 'sponsors') {
            $payload['variant']         = $window['variant'] ?? 'corner';
            $payload['rotate_seconds']  = (int) ($window['rotate_seconds'] ?? 6);
            $payload['corner_position'] = $window['corner_position'] ?? 'bottom-right';
            $payload['corner_size']     = $window['corner_size'] ?? 'm';
            $payload['items']           = $data->resolveSponsors($window);
        } elseif ($type === 'photowall') {
            $payload['items']         = $data->resolveSponsors($window);
            $payload['main_logo']     = ! empty($window['pw_main_logo'])
                ? \Illuminate\Support\Facades\Storage::url($window['pw_main_logo'])
                : ($base['logo'] ?? null);
            $num = fn ($k) => isset($window[$k]) && $window[$k] !== '' ? (float) $window[$k] : null;
            $payload['main_position'] = $window['pw_main_position'] ?? 'center';
            $payload['main_size']     = $window['pw_main_size'] ?? 'l';
            $payload['main_size_num'] = $num('pw_main_size_num');
            $payload['main_dx']       = $num('pw_main_dx') ?? 0;
            $payload['main_dy']       = $num('pw_main_dy') ?? 0;
            $payload['main_bg']       = (bool) ($window['pw_logo_bg'] ?? true);
            $payload['tile_size']     = $window['pw_tile_size'] ?? 'm';
            $payload['gap']           = $window['pw_gap'] ?? 'normal';
            $payload['layout_variant'] = $window['pw_layout'] ?? 'brick';
            $payload['bg_pattern']    = $window['pw_bg_pattern'] ?? 'solid';
            $payload['animate']       = $window['pw_animate'] ?? 'none';
            $payload['anim_speed']    = $num('pw_anim_speed');
            $payload['title']         = $window['pw_title'] ?? null;
            $payload['title_position'] = $window['pw_title_position'] ?? 'bottom-center';
            $payload['title_size']    = $window['pw_title_size'] ?? 'm';
            $payload['title_size_num'] = $num('pw_title_size_num');
            $payload['title_dx']      = $num('pw_title_dx') ?? 0;
            $payload['title_dy']      = $num('pw_title_dy') ?? 0;
            $payload['title_bg']      = (bool) ($window['pw_title_bg'] ?? true);
        } else {
            $resolved = $data->resolveWindow($tid, $window);
            if (empty($resolved['groups'])) {
                $resolved['stale'] = true;
            }
            $payload = array_merge($payload, $resolved);
        }

        return $payload;
    }

    /**
     * List the tournament IDs the overlays actually need, so the external
     * push bridge can follow the admin instead of a hardcoded ID. Same shared
     * secret as ingest.
     */
    public function wanted(Request $request): JsonResponse
    {
        $expected = config('services.overlay.ingest_token');

        if (! $expected || ! hash_equals($expected, (string) $request->header('X-Overlay-Token'))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $ids = Overlay::query()
            ->whereNotNull('tournament_external_id')
            ->where('tournament_external_id', '!=', '')
            ->pluck('tournament_external_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        return response()->json(['tournament_ids' => $ids]);
    }

    /**
     * Ingest a tournament snapshot pushed in by the external bridge.
     * Authenticated by a shared secret token (the host cannot reach the
     * Tournated API itself, so data is pushed in instead of pulled).
     */
    public function ingest(Request $request): JsonResponse
    {
        $expected = config('services.overlay.ingest_token');

        if (! $expected || ! hash_equals($expected, (string) $request->header('X-Overlay-Token'))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'tournament_id'        => 'required',
            'title'                => 'nullable|string',
            'categories'           => 'array',
            'groups_by_category'   => 'array',
            'category_stages'      => 'array',
            'brackets_by_category' => 'array',
            'matches'              => 'array',
            'participants_by_category' => 'array',
        ]);

        OverlaySnapshot::updateOrCreate(
            ['tournament_external_id' => (string) $validated['tournament_id']],
            ['payload' => [
                'title'                => $validated['title'] ?? null,
                'categories'           => $validated['categories'] ?? [],
                'groups_by_category'   => $validated['groups_by_category'] ?? [],
                'category_stages'      => $validated['category_stages'] ?? [],
                'brackets_by_category' => $validated['brackets_by_category'] ?? [],
                'matches'              => $validated['matches'] ?? [],
                'participants_by_category' => $validated['participants_by_category'] ?? [],
            ]],
        );

        return response()->json(['ok' => true]);
    }

}
