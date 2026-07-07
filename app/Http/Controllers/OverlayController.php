<?php

namespace App\Http\Controllers;

use App\Models\Overlay;
use App\Models\OverlaySnapshot;
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
        $score = $state['score'] ?? [];
        $config = $engine->config($window);
        $tid = (string) $overlay->tournament_external_id;
        $findMatch = fn ($id) => collect($data->matches($tid))->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $id);

        switch ($request->input('action', 'state')) {
            case 'point':
                if (! empty($score)) {
                    $score = $engine->point($config, $score, (int) $request->input('team'));
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
                $m = $findMatch($request->input('match_id'));
                if ($m) {
                    $score = $engine->init($config, [$m['team1'] ?? [], $m['team2'] ?? []]);
                    $state['score_match_id'] = $request->input('match_id');
                    $state['active_window_id'] = $window['id'];
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
            case 'stop':
                $state['active_window_id'] = null;
                break;
        }

        $state['score'] = $score;
        $overlay->state = $state;
        $overlay->save();

        $m = $findMatch($state['score_match_id'] ?? null) ?? [];

        return response()->json([
            'ok'       => true,
            'card'     => $data->resolveScore($window, $score, $m, $config),
            'status'   => $score['status'] ?? 'playing',
            'tiebreak' => ! empty($score['tiebreak']),
            'super_tiebreak' => ! empty($score['super_tiebreak']),
            'match_id' => $state['score_match_id'] ?? null,
            'active'   => ($state['active_window_id'] ?? null) === $window['id'],
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

    /** Play/stop a window from the control panel (token-authorised, CSRF-exempt). */
    public function controlAction(Overlay $overlay, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action'    => 'required|in:play,stop',
            'window_id' => 'nullable|string',
        ]);

        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['active_window_id'] = $validated['action'] === 'play'
            ? ($validated['window_id'] ?? null)
            : null;
        $overlay->state = $state;
        $overlay->save();

        return response()->json(['active_window_id' => $state['active_window_id']]);
    }

    public function data(Overlay $overlay, OverlayData $data): JsonResponse
    {
        $config = array_merge(Overlay::defaultConfig(), $overlay->config ?? []);
        $state  = array_merge(Overlay::defaultState(), $overlay->state ?? []);

        $logo = ! empty($config['logo']) ? \Illuminate\Support\Facades\Storage::url($config['logo']) : null;

        $tid = (string) $overlay->tournament_external_id;
        $tournamentTitle = $tid !== '' ? ($data->tournament($tid)['title'] ?? null) : null;

        $payload = [
            'title'            => $config['title'],
            'tournament_title' => $tournamentTitle,
            'colors'      => $config['colors'],
            'accent'      => $config['colors']['accent'] ?? '#C9A84C',
            'logo'        => $logo,
            'position'    => $config['position'],
            'columns'     => $config['visible_columns'],
            'next_match'  => $state['next_match'],
            'visible'     => false,
            'window_id'   => null,
            'window_type' => null,
            'stale'       => false,
        ];

        $activeId = $state['active_window_id'];
        if (! $activeId) {
            return response()->json($payload);
        }

        $window = collect($overlay->windows ?? [])->firstWhere('id', $activeId);
        if (! $window) {
            return response()->json($payload);
        }

        $payload['visible']     = true;
        $payload['window_id']   = $activeId;
        $payload['window_type'] = $window['type'] ?? 'groups';

        $payload['scrim'] = [
            'enabled' => (bool) ($window['scrim_enabled'] ?? false),
            'opacity' => (int) ($window['scrim_opacity'] ?? 55),
        ];

        $type = $window['type'] ?? 'groups';

        if ($type === 'schedule') {
            $payload['schedule_variant'] = $window['schedule_variant'] ?? 'by_court';
            $payload['schedule'] = $data->resolveSchedule((string) $overlay->tournament_external_id, $window);
        } elseif ($type === 'bracket') {
            $segments = $data->bracketSegmentsForCategory(
                (string) $overlay->tournament_external_id,
                (int) ($window['category_id'] ?? 0),
            );

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
            // Which category/group is being drawn (always shown on the board).
            $catName = null;
            foreach ($data->categories((string) $overlay->tournament_external_id) as $c) {
                if ((string) ($c['id'] ?? '') === (string) ($window['category_id'] ?? '')) {
                    $catName = $c['category']['name'] ?? null;
                }
            }
            $payload['draw']['category'] = $catName;
        } elseif ($type === 'score') {
            $scoreState = $state['score'] ?? [];
            $matchId = $state['score_match_id'] ?? null;
            $m = collect($data->matches((string) $overlay->tournament_external_id))
                ->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $matchId) ?? [];
            $payload['score'] = $data->resolveScore($window, $scoreState, $m, $data->scoreConfig($window));
        } elseif ($type === 'h2h') {
            $payload['h2h'] = $data->resolveH2h(
                (string) $overlay->tournament_external_id,
                $state['h2h_match_id'] ?? null,
                $window,
            );
            // Optional: show the live score (from the scorer) in the centre —
            // the exact same card the standalone "Rezultatas" overlay renders.
            if (! empty($state['h2h_show_score']) && ! empty($state['score']['teams'])) {
                $scoreWindow = collect($overlay->windows ?? [])->firstWhere('type', 'score') ?? [];
                $hm = collect($data->matches((string) $overlay->tournament_external_id))
                    ->first(fn ($x) => (string) ($x['id'] ?? '') === (string) ($state['h2h_match_id'] ?? '')) ?? [];
                $sc = $data->resolveScore($scoreWindow, $state['score'], $hm, $data->scoreConfig($scoreWindow));
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
        } else {
            $resolved = $data->resolveWindow((string) $overlay->tournament_external_id, $window);
            if (empty($resolved['groups'])) {
                $resolved['stale'] = true;
            }
            $payload = array_merge($payload, $resolved);
        }

        return response()->json($payload);
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
