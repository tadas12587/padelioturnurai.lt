<?php

namespace App\Http\Controllers;

use App\Models\Overlay;
use App\Services\TournatedClient;
use Illuminate\Http\JsonResponse;

class OverlayController extends Controller
{
    public function show(Overlay $overlay)
    {
        $view = $overlay->type === 'bracket' ? 'overlays.bracket' : 'overlays.group_standings';

        return view($view, ['overlay' => $overlay]);
    }

    public function data(Overlay $overlay, TournatedClient $client): JsonResponse
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
            $payload += $this->groupPayload($overlay, $state, $client);
        } else {
            $payload += $this->bracketPayload($overlay);
        }

        return response()->json($payload);
    }

    /** @param array<string,mixed> $state */
    private function groupPayload(Overlay $overlay, array $state, TournatedClient $client): array
    {
        $categoryId = $state['active_category_id'];
        if (! $categoryId) {
            return ['groups' => [], 'subgroup_count' => 0];
        }

        $raw = $client->groups((int) $categoryId);

        if ($state['active_group_id']) {
            $raw = array_values(array_filter($raw, fn ($g) => $g['id'] == $state['active_group_id']));
        }

        $groups = array_map(fn ($g) => [
            'id'   => $g['id'],
            'name' => $g['name'] ?? '',
            'rows' => $client->computeStandings($g),
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
