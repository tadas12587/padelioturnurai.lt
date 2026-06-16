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
        $view = $overlay->type === 'bracket' ? 'overlays.bracket' : 'overlays.group_standings';

        return view($view, ['overlay' => $overlay]);
    }

    public function data(Overlay $overlay, OverlayData $data): JsonResponse
    {
        $config = array_merge(Overlay::defaultConfig(), $overlay->config ?? []);
        $state  = array_merge(Overlay::defaultState(), $overlay->state ?? []);

        $payload = [
            'type'       => $overlay->type,
            'visible'    => (bool) $state['visible'],
            'title'      => $config['title'],
            'accent'     => $config['accent_color'],
            'logo'       => $config['logo'],
            'position'   => $config['position'],
            'columns'    => $config['visible_columns'],
            'next_match' => $state['next_match'],
            'stale'      => false,
        ];

        if (! $payload['visible']) {
            return response()->json($payload);
        }

        if ($overlay->type === 'group_standings') {
            $payload = array_merge($payload, $this->groupPayload($overlay, $state, $data));
        } else {
            $payload = array_merge($payload, $this->bracketPayload($overlay));
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
        ]);

        OverlaySnapshot::updateOrCreate(
            ['tournament_external_id' => (string) $validated['tournament_id']],
            ['payload' => [
                'title'              => $validated['title'] ?? null,
                'categories'         => $validated['categories'] ?? [],
                'groups_by_category' => $validated['groups_by_category'] ?? [],
            ]],
        );

        return response()->json(['ok' => true]);
    }

    /** @param array<string,mixed> $state */
    private function groupPayload(Overlay $overlay, array $state, OverlayData $data): array
    {
        $categoryId = $state['active_category_id'];
        if (! $categoryId) {
            return ['groups' => [], 'subgroup_count' => 0];
        }

        $raw = $data->groups((string) $overlay->tournament_external_id, (int) $categoryId);

        if (empty($raw)) {
            return ['groups' => [], 'subgroup_count' => 0, 'stale' => true];
        }

        if ($state['active_group_id']) {
            $raw = array_values(array_filter($raw, fn ($g) => $g['id'] == $state['active_group_id']));
        }

        $groups = array_map(fn ($g) => [
            'id'   => $g['id'],
            'name' => $g['name'] ?? '',
            'rows' => $data->computeStandings($g),
        ], $raw);

        return ['groups' => $groups, 'subgroup_count' => count($groups)];
    }

    private function bracketPayload(Overlay $overlay): array
    {
        $data = $overlay->bracket_data ?? ['rounds' => []];
        $rounds = $data['rounds'] ?? [];
        $drawSize = isset($rounds[0]['matches']) ? count($rounds[0]['matches']) * 2 : 0;

        return ['rounds' => $rounds, 'draw_size' => $drawSize];
    }
}
