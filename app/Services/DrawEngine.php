<?php

namespace App\Services;

/**
 * Pure draw-ceremony logic: computes slot layouts and applies draw/manual/
 * lock/undo/reset to a (config, state) pair. No DB or HTTP — every rule is
 * unit-testable. The runtime state lives in overlay.state['draws'][windowId].
 */
class DrawEngine
{
    /** Sentinel slot value for a walkover / empty draw position. */
    public const BYE = 'BYE';

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

        // PHP casts numeric-string array keys to int, so bracket slot keys come
        // back as ints — keep the public slot value a string for consistency.
        $slot = (string) $slot;

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

        // Teams with no pot fall into one band just above the deepest real pot,
        // so a draw still works (and never loops forever) when pot data is absent.
        $finite = [];
        foreach ($remaining as $t) {
            if ($usePots && ! empty($t['pot'])) {
                $finite[] = (int) $t['pot'];
            }
        }
        $lastPot = $finite ? max($finite) + 1 : 1;

        $potOf = fn ($t) => $usePots ? (empty($t['pot']) ? $lastPot : (int) $t['pot']) : 1;
        $candidates = $usePots
            ? array_values(array_filter($remaining, fn ($t) => $potOf($t) === $pot))
            : $remaining;

        // Active pot empty → advance to the next pot that still has teams.
        while ($usePots && empty($candidates)) {
            $pot++;
            if ($pot > $lastPot) {
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

    public function place(array $config, array $state, $teamId, string $slot): array
    {
        if (! array_key_exists($slot, $state['slots'])) {
            throw new \RuntimeException("Nėra tokios vietos: {$slot}.");
        }
        if ($state['slots'][$slot] !== null) {
            throw new \RuntimeException('Vieta jau užimta.');
        }
        // A real team occupies only one slot; a BYE may repeat across slots.
        // Compare as strings — random draws store int ids, the console sends strings.
        if ($teamId !== self::BYE) {
            foreach ($state['slots'] as $k => $tid) {
                if ($tid !== null && (string) $tid === (string) $teamId) {
                    $state['slots'][$k] = null;
                }
            }
        }
        $state['slots'][$slot] = $teamId;
        $state['current'] = ['team_id' => $teamId, 'slot' => (string) $slot];
        $state['history'][] = ['team_id' => $teamId, 'slot' => (string) $slot];
        $state['status'] = $this->poolEmpty($state) ? 'done' : 'idle';

        return $state;
    }

    public function lock(array $config, array $state, $teamId, string $slot): array
    {
        return $this->place($config, $state, $teamId, $slot);
    }

    public function undo(array $config, array $state): array
    {
        if (empty($state['history'])) {
            return $state;
        }
        $last = array_pop($state['history']);
        $state['slots'][$last['slot']] = null;
        $state['current'] = null;
        $state['status'] = 'idle';

        return $state;
    }

    public function reset(array $config, array $state): array
    {
        return $this->init($config, $state['teams']);
    }

    private function poolEmpty(array $state): bool
    {
        $teamIds = array_column($state['teams'], 'id');
        $placedReal = array_unique(array_filter(
            $state['slots'],
            fn ($t) => $t !== null && $t !== self::BYE && in_array($t, $teamIds),
        ));

        return count($placedReal) >= count($teamIds);
    }

    /** @return array{0:array,1:string,2:int} [team, slotKey, nextActivePot] */
    private function pickBracket(array $config, array $state, array $remaining, callable $rng): array
    {
        $n = (int) ($config['bracket_size'] ?? 0);
        $usePots = (bool) ($config['use_pots'] ?? false);

        // The unseeded band is one above the deepest seed band, so active_pot
        // stays a small, displayable number (no PHP_INT_MAX sentinel).
        $unseededPot = $this->bracketPotOfSeed(max(2, $n)) + 1;

        if (! $usePots) {
            $team = $remaining[$rng(count($remaining))];
            $free = array_values(array_filter(array_keys($state['slots']), fn ($k) => $state['slots'][$k] === null));
            if (empty($free)) {
                throw new \RuntimeException('Nėra laisvų vietų.');
            }

            return [$team, $free[$rng(count($free))], 1];
        }

        $potOf = function ($t) use ($unseededPot) {
            return empty($t['seed']) ? $unseededPot : $this->bracketPotOfSeed((int) $t['seed']);
        };

        $pot = (int) ($state['active_pot'] ?? 1);
        $candidates = array_values(array_filter($remaining, fn ($t) => $potOf($t) === $pot));
        while (empty($candidates)) {
            $pot++;
            if ($pot > $unseededPot) {
                throw new \RuntimeException('Krepšelio nepavyksta užpildyti.');
            }
            $candidates = array_values(array_filter($remaining, fn ($t) => $potOf($t) === $pot));
        }

        $team = $candidates[$rng(count($candidates))];

        // A seeded team uses its band's canonical anchor slots; unseeded use all free.
        $free = array_values(array_filter(array_keys($state['slots']), fn ($k) => $state['slots'][$k] === null));
        if (! empty($team['seed'])) {
            $band = $this->bracketPotOfSeed((int) $team['seed']);
            $bandSlots = [];
            foreach ($this->bracketSeedOrder($n) as $idx => $seed) {
                if ($this->bracketPotOfSeed($seed) === $band) {
                    $bandSlots[] = (string) ($idx + 1);
                }
            }
            $anchors = array_values(array_intersect($free, $bandSlots));
            if (! empty($anchors)) {
                $free = $anchors;
            }
        }
        if (empty($free)) {
            throw new \RuntimeException('Nėra laisvų vietų.');
        }

        return [$team, $free[$rng(count($free))], $pot];
    }
}
