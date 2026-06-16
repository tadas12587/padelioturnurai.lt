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

        $payload = [
            'title'       => $config['title'],
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

        if (($window['type'] ?? 'groups') === 'bracket') {
            $rounds = $window['bracket_data']['rounds'] ?? [];
            $payload['rounds']    = $rounds;
            $payload['draw_size'] = isset($rounds[0]['matches']) ? count($rounds[0]['matches']) * 2 : 0;
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
            'tournament_id'      => 'required',
            'title'              => 'nullable|string',
            'categories'         => 'array',
            'groups_by_category' => 'array',
            'category_stages'    => 'array',
        ]);

        OverlaySnapshot::updateOrCreate(
            ['tournament_external_id' => (string) $validated['tournament_id']],
            ['payload' => [
                'title'              => $validated['title'] ?? null,
                'categories'         => $validated['categories'] ?? [],
                'groups_by_category' => $validated['groups_by_category'] ?? [],
                'category_stages'    => $validated['category_stages'] ?? [],
            ]],
        );

        return response()->json(['ok' => true]);
    }

}
