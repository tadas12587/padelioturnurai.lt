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

    /** @return list<int> seed number at each physical slot (0-based index) */
    public function bracketSeedOrder(int $n): array
    {
        $order = [1, 2];
        while (count($order) < $n) {
            $sum = count($order) * 2 + 1;
            $next = [];
            foreach ($order as $s) {
                $next[] = $s;
                $next[] = $sum - $s;
            }
            $order = $next;
        }

        return $order;
    }

    public function bracketPotOfSeed(int $seed): int
    {
        return max(1, (int) ceil(log($seed, 2)));
    }

    /** Physical slot key ("1".."N") that a given seed occupies. */
    public function bracketSlotForSeed(int $n, int $seed): string
    {
        $idx = array_search($seed, $this->bracketSeedOrder($n), true);

        return (string) ($idx + 1);
    }

    /** Apply one random draw. @throws \RuntimeException when nothing can be drawn. */
    public function drawNext(array $config, array $state, ?callable $rng = null): array
    {
        $rng ??= fn (int $count) => random_int(0, max(0, $count - 1));

        $placed = array_filter($state['slots'], fn ($t) => $t !== null);
        $placedIds = array_values($placed);
        $remaining = array_values(array_filter(
            $state['teams'],
            fn ($t) => ! in_array($t['id'], $placedIds, true),
        ));

        if (empty($remaining)) {
            throw new \RuntimeException('Traukimas baigtas — nebėra komandų.');
        }

        [$team, $slot, $nextPot] = (($config['format'] ?? 'groups') === 'bracket')
            ? $this->pickBracket($config, $state, $remaining, $rng)
            : $this->pickGroups($config, $state, $remaining, $rng);

        $state['slots'][$slot] = $team['id'];
        $state['current'] = ['team_id' => $team['id'], 'slot' => $slot];
        $state['history'][] = ['team_id' => $team['id'], 'slot' => $slot];
        $state['active_pot'] = $nextPot;

        $stillLeft = count($remaining) - 1;
        $state['status'] = $stillLeft === 0 ? 'done' : 'idle';

        return $state;
    }

    /** @return array{0:array,1:string,2:int} [team, slotKey, nextActivePot] */
    private function pickGroups(array $config, array $state, array $remaining, callable $rng): array
    {
        $usePots = (bool) ($config['use_pots'] ?? false);
        $layout = $this->layout($config);
        $pot = (int) ($state['active_pot'] ?? 1);

        // Candidates in the active pot (or all remaining when pots are off).
        $potOf = fn ($t) => $usePots ? (int) ($t['pot'] ?? PHP_INT_MAX) : 1;
        $candidates = $usePots
            ? array_values(array_filter($remaining, fn ($t) => $potOf($t) === $pot))
            : $remaining;

        // Active pot empty → advance to the next pot that still has teams.
        while ($usePots && empty($candidates)) {
            $pot++;
            if ($pot > 1000) {
                throw new \RuntimeException('Krepšelio nepavyksta užpildyti.');
            }
            $candidates = array_values(array_filter($remaining, fn ($t) => $potOf($t) === $pot));
        }

        $team = $candidates[$rng(count($candidates))];

        // Groups that have a free slot AND no team from this pot yet (when pots on).
        $eligible = [];
        foreach ($layout['groups'] as $grp) {
            $free = array_values(array_filter($grp['slots'], fn ($k) => $state['slots'][$k] === null));
            if (empty($free)) {
                continue;
            }
            if ($usePots) {
                $hasPot = false;
                foreach ($grp['slots'] as $k) {
                    $tid = $state['slots'][$k];
                    if ($tid !== null && $potOf($this->teamById($state, $tid)) === $pot) {
                        $hasPot = true;
                        break;
                    }
                }
                if ($hasPot) {
                    continue;
                }
            }
            $eligible[] = ['group' => $grp, 'free' => $free];
        }
        if (empty($eligible)) { // pots constraint blocked everything → relax to any free group
            foreach ($layout['groups'] as $grp) {
                $free = array_values(array_filter($grp['slots'], fn ($k) => $state['slots'][$k] === null));
                if ($free) {
                    $eligible[] = ['group' => $grp, 'free' => $free];
                }
            }
        }
        if (empty($eligible)) {
            throw new \RuntimeException('Nėra laisvų vietų.');
        }

        $chosen = $eligible[$rng(count($eligible))];

        return [$team, $chosen['free'][0], $pot];
    }

    private function teamById(array $state, $id): array
    {
        foreach ($state['teams'] as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }

        return ['id' => $id, 'pot' => null];
    }
}
