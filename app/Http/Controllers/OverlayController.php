<?php

namespace App\Http\Controllers;

use App\Models\Overlay;
use App\Models\OverlaySnapshot;
use App\Services\OverlayData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverlayController extends Controller
{
    public function show(Overlay $overlay)
    {
        return view('overlays.window', ['overlay' => $overlay]);
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
        } elseif ($type === 'sponsors') {
            $payload['variant']        = $window['variant'] ?? 'corner';
            $payload['rotate_seconds'] = (int) ($window['rotate_seconds'] ?? 6);
            $payload['items']          = $data->resolveSponsors($window);
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
            ]],
        );

        return response()->json(['ok' => true]);
    }

}
