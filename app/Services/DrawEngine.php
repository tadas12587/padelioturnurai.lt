<?php

namespace App\Services;

/**
 * Pure draw-ceremony logic: computes slot layouts and applies draw/manual/
 * lock/undo/reset to a (config, state) pair. No DB or HTTP — every rule is
 * unit-testable. The runtime state lives in overlay.state['draws'][windowId].
 */
class DrawEngine
{
    /** @return array<string,mixed> */
    public function layout(array $config): array
    {
        if (($config['format'] ?? 'groups') === 'bracket') {
            $n = (int) ($config['bracket_size'] ?? 0);
            $pairs = [];
            for ($i = 1; $i <= $n; $i += 2) {
                $pairs[] = [(string) $i, (string) ($i + 1)];
            }

            return ['format' => 'bracket', 'pairs' => $pairs];
        }

        $count = (int) ($config['group_count'] ?? 0);
        $size = (int) ($config['group_size'] ?? 0);
        $groups = [];
        for ($g = 0; $g < $count; $g++) {
            $label = chr(ord('A') + $g);
            $slots = [];
            for ($p = 1; $p <= $size; $p++) {
                $slots[] = $label . $p;
            }
            $groups[] = ['label' => $label, 'slots' => $slots];
        }

        return ['format' => 'groups', 'groups' => $groups];
    }

    /** @return list<string> ordered slot keys */
    public function slotKeys(array $config): array
    {
        $layout = $this->layout($config);
        if ($layout['format'] === 'bracket') {
            $keys = [];
            foreach ($layout['pairs'] as $pair) {
                array_push($keys, ...$pair);
            }

            return $keys;
        }

        if (empty($layout['groups'])) {
            return [];
        }

        return array_merge(...array_map(fn ($g) => $g['slots'], $layout['groups']));
    }

    /** @return array<string,mixed> */
    public function init(array $config, array $teams): array
    {
        $slots = [];
        foreach ($this->slotKeys($config) as $key) {
            $slots[$key] = null;
        }

        return [
            'teams' => array_values($teams),
            'slots' => $slots,
            'current' => null,
            'history' => [],
            'active_pot' => 1,
            'status' => 'idle',
        ];
    }
}
