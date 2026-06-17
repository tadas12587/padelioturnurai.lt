# Draw Ceremony Overlay — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a live draw/seeding ceremony as a new `type:'draw'` overlay window — an operator draws teams (random with a roulette reveal, or manual) into group tables or a seeded bracket, with a tournament header and sponsor strip on a near-fullscreen board that keeps one corner clear for the live camera.

**Architecture:** A pure-PHP `DrawEngine` service computes slot layouts and applies draw/manual/lock/undo/reset to a `(config, state)` pair. The runtime board state lives in `overlay.state['draws'][windowId]` (authored live, not projected from the Tournated snapshot). The participant pool is copied once from the snapshot's new `participants_by_category` (pushed by push.js) or entered manually. A Filament "Traukimo valdymas" page mutates that state; the existing `/overlay/{token}/data` endpoint gains a `draw` branch; the existing `window.blade.php` renderer gains a `draw` branch that polls at ~1s and plays the reveal.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11 (SQLite `:memory:`), Blade + vanilla JS, Node ESM (push.js).

**Spec:** `docs/superpowers/specs/2026-06-17-draw-ceremony-overlay-design.md`

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `app/Services/DrawEngine.php` | Pure draw logic: slot layout, pots/bands, drawNext, place, lock, undo, reset, bracket seed order | Create |
| `tests/Unit/DrawEngineTest.php` | Unit tests for every engine rule | Create |
| `app/Http/Controllers/OverlayController.php` | `data()` gains `draw` branch; `ingest()` accepts `participants_by_category` | Modify |
| `app/Services/OverlayData.php` | `participants(tid, catId)` reader; `resolveDraw($overlay, $window)` payload assembler | Modify |
| `app/Filament/Resources/OverlayResource.php` | `'draw'` window type + its config fields | Modify |
| `app/Filament/Pages/DrawControlPage.php` | Operator console: load participants, draw/manual/lock/undo/reset, play/stop | Create |
| `resources/views/filament/pages/draw-control.blade.php` | Console view | Create |
| `resources/views/overlays/window.blade.php` | `draw` render branch + CSS | Modify |
| `resources/views/overlays/base.blade.php` | Per-window poll interval; signature includes draw state | Modify |
| `tools/overlay-push/push.js` | Fetch participants per category → `participants_by_category` | Modify |
| `tests/Feature/OverlayEndpointTest.php` | `data()` draw-branch feature tests | Modify |
| `tests/Feature/DrawControlTest.php` | Console action tests | Create |

**State shape** written to `overlay.state['draws'][$windowId]`:
```
{ teams:[{id,name,pot,seed,locked_slot}], slots:{key:teamId|null},
  current:{team_id,slot}|null, history:[{team_id,slot}], active_pot:int, status:'idle'|'done' }
```

**Engine conventions (lock these — tests assert them):**
- Group slot keys: `A1..A{group_size}`, `B1..`, … (letters A, B, C…).
- Bracket slot keys: `"1".."N"` = physical top-to-bottom positions; first-round pairs are consecutive `(1,2),(3,4),…`.
- `bracketSeedOrder($n)` (recursive doubling) returns the seed at each physical slot:
  `n=4 → [1,4,2,3]`, `n=8 → [1,8,4,5,2,7,3,6]`.
- Bracket pot of a seed: `max(1, ceil(log2(seed)))` → seeds {1,2}=pot1, {3,4}=pot2, {5..8}=pot3, {9..16}=pot4.
- Groups use `team.pot` directly. Teams with no pot/seed fall to the **last** pot (drawn last into remaining slots).
- `drawNext` takes an injected `$rng` callable `fn(int $count): int` (returns `0..count-1`) so tests are deterministic; production passes `null` → `random_int`.

---

## Task 1: DrawEngine — slot layout & init

**Files:**
- Create: `app/Services/DrawEngine.php`
- Test: `tests/Unit/DrawEngineTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit;

use App\Services\DrawEngine;
use Tests\TestCase;

class DrawEngineTest extends TestCase
{
    private DrawEngine $e;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e = new DrawEngine();
    }

    public function test_groups_layout_builds_lettered_slot_keys(): void
    {
        $layout = $this->e->layout(['format' => 'groups', 'group_count' => 2, 'group_size' => 3]);

        $this->assertSame('groups', $layout['format']);
        $this->assertSame('A', $layout['groups'][0]['label']);
        $this->assertSame(['A1', 'A2', 'A3'], $layout['groups'][0]['slots']);
        $this->assertSame(['B1', 'B2', 'B3'], $layout['groups'][1]['slots']);
    }

    public function test_bracket_layout_pairs_consecutive_physical_slots(): void
    {
        $layout = $this->e->layout(['format' => 'bracket', 'bracket_size' => 4]);

        $this->assertSame('bracket', $layout['format']);
        $this->assertSame([['1', '2'], ['3', '4']], $layout['pairs']);
    }

    public function test_init_creates_empty_slots_and_idle_status(): void
    {
        $config = ['format' => 'groups', 'group_count' => 2, 'group_size' => 2];
        $teams = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']];

        $state = $this->e->init($config, $teams);

        $this->assertSame(['A1' => null, 'A2' => null, 'B1' => null, 'B2' => null], $state['slots']);
        $this->assertCount(2, $state['teams']);
        $this->assertNull($state['current']);
        $this->assertSame([], $state['history']);
        $this->assertSame(1, $state['active_pot']);
        $this->assertSame('idle', $state['status']);
    }
}
```

- [ ] **Step 2: Run, verify fail**

Run: `php artisan test --filter=DrawEngineTest`
Expected: FAIL (class `DrawEngine` not found).

- [ ] **Step 3: Implement `layout()` and `init()`**

```php
<?php

namespace App\Services;

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
```

- [ ] **Step 4: Run, verify pass**

Run: `php artisan test --filter=DrawEngineTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/DrawEngine.php tests/Unit/DrawEngineTest.php
git commit -m "feat(draw): DrawEngine slot layout and init"
```

---

## Task 2: DrawEngine — bracket seed order & pot bands

**Files:**
- Modify: `app/Services/DrawEngine.php`
- Test: `tests/Unit/DrawEngineTest.php`

- [ ] **Step 1: Add failing tests**

```php
public function test_bracket_seed_order_recursive_doubling(): void
{
    $this->assertSame([1, 2], $this->e->bracketSeedOrder(2));
    $this->assertSame([1, 4, 2, 3], $this->e->bracketSeedOrder(4));
    $this->assertSame([1, 8, 4, 5, 2, 7, 3, 6], $this->e->bracketSeedOrder(8));
}

public function test_bracket_pot_of_seed_bands(): void
{
    $this->assertSame(1, $this->e->bracketPotOfSeed(1));
    $this->assertSame(1, $this->e->bracketPotOfSeed(2));
    $this->assertSame(2, $this->e->bracketPotOfSeed(3));
    $this->assertSame(2, $this->e->bracketPotOfSeed(4));
    $this->assertSame(3, $this->e->bracketPotOfSeed(5));
    $this->assertSame(3, $this->e->bracketPotOfSeed(8));
    $this->assertSame(4, $this->e->bracketPotOfSeed(9));
}

public function test_bracket_slot_for_seed_maps_to_physical_position(): void
{
    // n=8 order [1,8,4,5,2,7,3,6]: seed 1 → slot "1", seed 2 → slot "5".
    $this->assertSame('1', $this->e->bracketSlotForSeed(8, 1));
    $this->assertSame('5', $this->e->bracketSlotForSeed(8, 2));
    $this->assertSame('7', $this->e->bracketSlotForSeed(8, 3));
}
```

- [ ] **Step 2: Run, verify fail** — `php artisan test --filter=DrawEngineTest` → FAIL (methods missing).

- [ ] **Step 3: Implement**

```php
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
```

- [ ] **Step 4: Run, verify pass** — 6 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/DrawEngine.php tests/Unit/DrawEngineTest.php
git commit -m "feat(draw): bracket seed order and pot bands"
```

---

## Task 3: DrawEngine — drawNext for groups (pots, one-per-group)

**Files:**
- Modify: `app/Services/DrawEngine.php`
- Test: `tests/Unit/DrawEngineTest.php`

- [ ] **Step 1: Add failing tests**

```php
public function test_groups_draw_distributes_pot_one_per_group_then_advances(): void
{
    $config = ['format' => 'groups', 'group_count' => 2, 'group_size' => 2, 'use_pots' => true];
    $teams = [
        ['id' => 1, 'name' => 'P1a', 'pot' => 1], ['id' => 2, 'name' => 'P1b', 'pot' => 1],
        ['id' => 3, 'name' => 'P2a', 'pot' => 2], ['id' => 4, 'name' => 'P2b', 'pot' => 2],
    ];
    $state = $this->e->init($config, $teams);
    $rng = fn (int $c) => 0; // always first candidate / first group

    $state = $this->e->drawNext($config, $state, $rng);
    $this->assertSame(1, $state['slots']['A1']);          // pot1 team → group A pos1
    $this->assertSame(['team_id' => 1, 'slot' => 'A1'], $state['current']);

    $state = $this->e->drawNext($config, $state, $rng);
    $this->assertSame(2, $state['slots']['B1']);          // pot1 second team → group B (A already has pot1)

    $state = $this->e->drawNext($config, $state, $rng);
    $this->assertSame(2, $state['active_pot']);           // pot1 exhausted → pot2
    $this->assertSame(3, $state['slots']['A2']);          // pot2 → group A next free pos
}

public function test_draw_marks_done_when_pool_empty(): void
{
    $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 1, 'use_pots' => true];
    $state = $this->e->init($config, [['id' => 1, 'name' => 'X', 'pot' => 1]]);
    $state = $this->e->drawNext($config, $state, fn (int $c) => 0);

    $this->assertSame('done', $state['status']);
    $this->assertSame(1, $state['slots']['A1']);
}

public function test_draw_throws_when_already_done(): void
{
    $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 1];
    $state = $this->e->init($config, [['id' => 1, 'name' => 'X', 'pot' => 1]]);
    $state = $this->e->drawNext($config, $state, fn (int $c) => 0);

    $this->expectException(\RuntimeException::class);
    $this->e->drawNext($config, $state, fn (int $c) => 0);
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement `drawNext` + group helpers**

```php
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
```

> `pickBracket()` is added in Task 4 — until then, a bracket draw would error. That is fine; Task 3 only tests groups.

- [ ] **Step 4: Run, verify pass.**

- [ ] **Step 5: Commit**

```bash
git add app/Services/DrawEngine.php tests/Unit/DrawEngineTest.php
git commit -m "feat(draw): random group draw with pot distribution"
```

---

## Task 4: DrawEngine — drawNext for bracket (seed bands)

**Files:**
- Modify: `app/Services/DrawEngine.php`
- Test: `tests/Unit/DrawEngineTest.php`

- [ ] **Step 1: Add failing tests**

```php
public function test_bracket_draw_places_seeds_at_band_anchor_slots(): void
{
    $config = ['format' => 'bracket', 'bracket_size' => 4, 'use_pots' => true];
    // n=4 order [1,4,2,3]: seed1→slot"1", seed2→slot"2"? -> slot index: seed1 idx0 ("1"), seed2 idx2 ("3").
    $teams = [
        ['id' => 10, 'name' => 'S1', 'seed' => 1],
        ['id' => 20, 'name' => 'S2', 'seed' => 2],
        ['id' => 30, 'name' => 'U1'],
        ['id' => 40, 'name' => 'U2'],
    ];
    $state = $this->e->init($config, $teams);
    $rng = fn (int $c) => 0;

    $state = $this->e->drawNext($config, $state, $rng); // pot1 seed → its anchor slot
    $this->assertSame(10, $state['slots']['1']);         // seed1 anchor = physical slot 1

    $state = $this->e->drawNext($config, $state, $rng);
    $this->assertSame(20, $state['slots']['3']);         // seed2 anchor = physical slot 3 (idx2)

    $state = $this->e->drawNext($config, $state, $rng);  // unseeded → remaining free slot
    $this->assertContains($state['current']['slot'], ['2', '4']);
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement `pickBracket`**

```php
/** @return array{0:array,1:string,2:int} */
private function pickBracket(array $config, array $state, array $remaining, callable $rng): array
{
    $n = (int) ($config['bracket_size'] ?? 0);
    $usePots = (bool) ($config['use_pots'] ?? false);
    $pot = (int) ($state['active_pot'] ?? 1);

    $potOf = function ($t) use ($usePots) {
        if (! $usePots || empty($t['seed'])) {
            return PHP_INT_MAX; // unseeded → last band
        }

        return $this->bracketPotOfSeed((int) $t['seed']);
    };

    $candidates = array_values(array_filter($remaining, fn ($t) => $potOf($t) === $pot));
    while ($usePots && empty($candidates)) {
        $pot = $pot === PHP_INT_MAX ? PHP_INT_MAX : $pot + 1;
        $unseededLeft = array_values(array_filter($remaining, fn ($t) => $potOf($t) === PHP_INT_MAX));
        $seededLeft = array_values(array_filter($remaining, fn ($t) => $potOf($t) < PHP_INT_MAX && $potOf($t) >= $pot));
        if (empty($seededLeft)) {
            $pot = PHP_INT_MAX;
            $candidates = $unseededLeft;
            break;
        }
        $candidates = array_values(array_filter($remaining, fn ($t) => $potOf($t) === $pot));
    }
    if (! $usePots) {
        $candidates = $remaining;
    }

    $team = $candidates[$rng(count($candidates))];

    // Anchor slots: a seeded team uses its band's canonical slots; unseeded use all free slots.
    $free = array_values(array_filter(array_keys($state['slots']), fn ($k) => $state['slots'][$k] === null));
    if ($usePots && ! empty($team['seed'])) {
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

    $slot = $free[$rng(count($free))];
    $nextPot = $usePots ? $pot : 1;

    return [$team, $slot, $nextPot];
}
```

- [ ] **Step 4: Run, verify pass.**

- [ ] **Step 5: Commit**

```bash
git add app/Services/DrawEngine.php tests/Unit/DrawEngineTest.php
git commit -m "feat(draw): random bracket draw with seed bands"
```

---

## Task 5: DrawEngine — place, lock, undo, reset

**Files:**
- Modify: `app/Services/DrawEngine.php`
- Test: `tests/Unit/DrawEngineTest.php`

- [ ] **Step 1: Add failing tests**

```php
public function test_manual_place_sets_slot_and_records_history(): void
{
    $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 2];
    $state = $this->e->init($config, [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);

    $state = $this->e->place($config, $state, 2, 'A2');

    $this->assertSame(2, $state['slots']['A2']);
    $this->assertSame(['team_id' => 2, 'slot' => 'A2'], end($state['history']));
}

public function test_place_rejects_occupied_slot(): void
{
    $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 2];
    $state = $this->e->init($config, [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
    $state = $this->e->place($config, $state, 1, 'A1');

    $this->expectException(\RuntimeException::class);
    $this->e->place($config, $state, 2, 'A1');
}

public function test_undo_frees_last_slot_and_returns_team_to_pool(): void
{
    $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 2, 'use_pots' => false];
    $state = $this->e->init($config, [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
    $state = $this->e->drawNext($config, $state, fn (int $c) => 0);
    $slot = $state['current']['slot'];

    $state = $this->e->undo($config, $state);

    $this->assertNull($state['slots'][$slot]);
    $this->assertSame([], $state['history']);
    $this->assertSame('idle', $state['status']);
}

public function test_reset_clears_all_slots_but_keeps_teams(): void
{
    $config = ['format' => 'groups', 'group_count' => 1, 'group_size' => 2];
    $state = $this->e->init($config, [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
    $state = $this->e->place($config, $state, 1, 'A1');

    $state = $this->e->reset($config, $state);

    $this->assertSame(['A1' => null, 'A2' => null], $state['slots']);
    $this->assertCount(2, $state['teams']);
    $this->assertSame([], $state['history']);
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**

```php
public function place(array $config, array $state, $teamId, string $slot): array
{
    if (! array_key_exists($slot, $state['slots'])) {
        throw new \RuntimeException("Nėra tokios vietos: {$slot}.");
    }
    if ($state['slots'][$slot] !== null) {
        throw new \RuntimeException('Vieta jau užimta.');
    }
    // Remove the team from any slot it currently occupies.
    foreach ($state['slots'] as $k => $tid) {
        if ($tid === $teamId) {
            $state['slots'][$k] = null;
        }
    }
    $state['slots'][$slot] = $teamId;
    $state['current'] = ['team_id' => $teamId, 'slot' => $slot];
    $state['history'][] = ['team_id' => $teamId, 'slot' => $slot];
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
    $placed = array_values(array_filter($state['slots'], fn ($t) => $t !== null));

    return count($placed) >= count($state['teams']);
}
```

- [ ] **Step 4: Run, verify pass** (all DrawEngine tests green).

- [ ] **Step 5: Commit**

```bash
git add app/Services/DrawEngine.php tests/Unit/DrawEngineTest.php
git commit -m "feat(draw): manual place, lock, undo, reset"
```

---

## Task 6: Participants pipeline — push.js, ingest, OverlayData reader

**Files:**
- Modify: `tools/overlay-push/push.js`
- Modify: `app/Http/Controllers/OverlayController.php:135-155`
- Modify: `app/Services/OverlayData.php`
- Test: `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Write failing feature test (ingest accepts + OverlayData reads participants)**

Add to `OverlayEndpointTest`:

```php
public function test_ingest_stores_participants_by_category(): void
{
    config(['services.overlay.ingest_token' => 'secret']);

    $this->postJson('/overlay/ingest', [
        'tournament_id' => '10424',
        'participants_by_category' => [
            '53636' => [['id' => 1, 'name' => 'Garcia / Lopez', 'seed' => 1]],
        ],
    ], ['X-Overlay-Token' => 'secret'])->assertOk();

    $teams = app(\App\Services\OverlayData::class)->participants('10424', 53636);
    $this->assertSame('Garcia / Lopez', $teams[0]['name']);
    $this->assertSame(1, $teams[0]['seed']);
}
```

- [ ] **Step 2: Run, verify fail** — `php artisan test --filter=test_ingest_stores_participants_by_category` → FAIL.

- [ ] **Step 3a: Extend `ingest()` validation + storage** (`OverlayController.php`)

In the `$request->validate([...])` array add:
```php
'participants_by_category' => 'array',
```
In the `OverlaySnapshot::updateOrCreate` payload add:
```php
'participants_by_category' => $validated['participants_by_category'] ?? [],
```

- [ ] **Step 3b: Add `participants()` reader** (`OverlayData.php`, near `groups()`)

```php
/** @return array<int,mixed> Frozen-pool source: teams of a category from the snapshot. */
public function participants(string $tournamentId, int $categoryId): array
{
    $byCat = $this->payload($tournamentId)['participants_by_category'] ?? [];

    return $byCat[(string) $categoryId] ?? [];
}
```

- [ ] **Step 3c: push.js — fetch participants per category**

After `fetchGroups` (around line 63) add:
```js
// ── Vienos kategorijos dalyviai (poros) ─────────────────────
async function fetchParticipants(categoryId) {
  const data = await gql(`{
    entries(filter: { tournamentCategory: ${categoryId} }) {
      id seed registrationRequest { users { user { name surname } } }
    }
  }`);
  const pairName = (e) => (e.registrationRequest?.users || [])
    .map((u) => `${u.user?.name || ''} ${u.user?.surname || ''}`.trim()).filter(Boolean).join(' / ');
  return (data.entries || []).map((e) => ({
    id: e.id,
    name: pairName(e) || `#${e.id}`,
    seed: e.seed ?? null,
    pot: null,
  }));
}
```
In `pushOnce`, alongside the existing per-category loop, build `participantsByCategory`:
```js
const participantsByCategory = {};
for (const cat of categories) {
  try { participantsByCategory[String(cat.id)] = await fetchParticipants(cat.id); }
  catch (e) { participantsByCategory[String(cat.id)] = []; }
}
```
Add `participants_by_category: participantsByCategory,` to the `snapshot` object.

> Note: confirm the Tournated `entries` field name + `seed` against a live category (e.g. `https://play.padel.lt/tournament/10424/participants?category=53636`) with a throwaway `gql` dump before relying on it; adjust field names if the schema differs. `registrationRequest.users` matches the shape already used in `fetchGroups`.

- [ ] **Step 4: Run, verify pass** — feature test green. (push.js verified manually in Task 11.)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OverlayController.php app/Services/OverlayData.php tools/overlay-push/push.js tests/Feature/OverlayEndpointTest.php
git commit -m "feat(draw): push and store participants_by_category"
```

---

## Task 7: Admin form — draw window type & config fields

**Files:**
- Modify: `app/Filament/Resources/OverlayResource.php`

No automated test (Filament form schema). Follow the existing per-type `visible()` pattern.

- [ ] **Step 1: Add `'draw'` to the window type Select options** (`OverlayResource.php:116`)

```php
->options(['groups' => 'Grupės', 'bracket' => 'Brackets', 'draw' => 'Traukimas', 'sponsors' => 'Rėmėjai', 'schedule' => 'Tvarkaraštis'])
```

- [ ] **Step 2: Add draw fields** (inside the window Repeater `schema`, after the schedule fields, before the sponsors fields). All gated with `visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw')`:

```php
Select::make('category_id')
    ->label('Kategorija (traukimui)')
    ->live()
    ->options(function ($livewire) {
        $tid = data_get($livewire, 'data.tournament_external_id');
        if (! $tid) {
            return [];
        }
        $out = [];
        foreach (app(OverlayData::class)->categories((string) $tid) as $c) {
            $out[$c['id']] = $c['category']['name'] ?? ('#' . $c['id']);
        }
        return $out;
    })
    ->helperText('Iš čia užkrausi dalyvius valdymo puslapyje.')
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),

Select::make('format')
    ->label('Formatas')
    ->options(['groups' => 'Grupių lentelės', 'bracket' => 'Bracket (sėklavimas)'])
    ->default('groups')->live()
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),

TextInput::make('group_count')->label('Grupių skaičius')->numeric()->minValue(1)->default(4)
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw' && ($get('format') ?? 'groups') === 'groups'),
TextInput::make('group_size')->label('Komandų grupėje')->numeric()->minValue(2)->default(4)
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw' && ($get('format') ?? 'groups') === 'groups'),
Select::make('bracket_size')->label('Bracket dydis')->options([8 => 8, 16 => 16, 32 => 32])->default(16)
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw' && ($get('format') ?? 'groups') === 'bracket'),

Toggle::make('use_pots')->label('Naudoti krepšelius / sėklas')->default(true)
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
Select::make('camera_corner')->label('Kameros kampas (skaidrus)')
    ->options(['bottom-right' => 'Apačia — dešinė', 'bottom-left' => 'Apačia — kairė', 'top-right' => 'Viršus — dešinė', 'top-left' => 'Viršus — kairė'])
    ->default('bottom-right')
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
Toggle::make('show_tournament')->label('Rodyti turnyro logo + pavadinimą')->default(true)
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
Select::make('sponsor_ids')->label('Rėmėjai iš sąrašo')->multiple()
    ->options(fn () => \App\Models\Sponsor::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
FileUpload::make('images')->label('Arba įkelk rėmėjų logotipus')
    ->image()->multiple()->reorderable()->disk('public')->directory('overlay-sponsors')
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
```

- [ ] **Step 3: Manual verify** — `php artisan serve`, open an overlay edit page, add a window, pick "Traukimas", confirm groups/bracket fields toggle correctly.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/OverlayResource.php
git commit -m "feat(draw): admin form fields for the draw window"
```

---

## Task 8: Controller `data()` — draw branch payload

**Files:**
- Modify: `app/Services/OverlayData.php` (add `resolveDraw`)
- Modify: `app/Http/Controllers/OverlayController.php:62-92`
- Test: `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Write failing feature test**

```php
public function test_data_returns_draw_window_board(): void
{
    OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
        'title' => 'Padelio Turnyras',
        'participants_by_category' => ['53636' => [['id' => 1, 'name' => 'A / B', 'pot' => 1]]],
    ]]);

    $overlay = Overlay::create([
        'name' => 'D', 'type' => 'group_standings', 'tournament_external_id' => '10424',
        'windows' => [[
            'id' => 'w1', 'type' => 'draw', 'name' => 'Traukimas', 'category_id' => 53636,
            'format' => 'groups', 'group_count' => 2, 'group_size' => 2, 'use_pots' => true,
            'camera_corner' => 'bottom-right', 'sponsor_ids' => [], 'images' => [],
        ]],
        'state' => [
            'active_window_id' => 'w1', 'next_match' => '',
            'draws' => ['w1' => [
                'teams' => [['id' => 1, 'name' => 'A / B', 'pot' => 1]],
                'slots' => ['A1' => 1, 'A2' => null, 'B1' => null, 'B2' => null],
                'current' => ['team_id' => 1, 'slot' => 'A1'], 'history' => [['team_id' => 1, 'slot' => 'A1']],
                'active_pot' => 1, 'status' => 'idle',
            ]],
        ],
    ]);

    $this->getJson("/overlay/{$overlay->token}/data")
        ->assertOk()
        ->assertJson(['visible' => true, 'window_type' => 'draw'])
        ->assertJsonPath('draw.format', 'groups')
        ->assertJsonPath('draw.camera_corner', 'bottom-right')
        ->assertJsonPath('draw.board.0.label', 'A')
        ->assertJsonPath('draw.slots.A1.name', 'A / B')
        ->assertJsonPath('draw.current.name', 'A / B')
        ->assertJsonPath('draw.current.slot', 'A1');
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3a: Add `resolveDraw()` to `OverlayData.php`**

```php
/**
 * Assemble the draw-window payload from the live runtime state + config.
 *
 * @param  array<string,mixed>  $window
 * @return array<string,mixed>
 */
public function resolveDraw(array $window, array $drawState): array
{
    $engine = app(\App\Services\DrawEngine::class);
    $layout = $engine->layout($window);
    $teams = collect($drawState['teams'] ?? [])->keyBy('id');

    $nameOf = fn ($id) => $id === null ? null
        : ['id' => $id, 'name' => $teams[$id]['name'] ?? ('#' . $id)];

    $slots = [];
    foreach (($drawState['slots'] ?? []) as $key => $tid) {
        $slots[$key] = $nameOf($tid);
    }

    $placedIds = array_values(array_filter($drawState['slots'] ?? [], fn ($t) => $t !== null));
    $pool = collect($drawState['teams'] ?? [])
        ->reject(fn ($t) => in_array($t['id'], $placedIds, true))
        ->map(fn ($t) => ['id' => $t['id'], 'name' => $t['name'] ?? ('#' . $t['id'])])
        ->values()->all();

    $current = $drawState['current'] ?? null;
    if ($current) {
        $current = ['name' => $teams[$current['team_id']]['name'] ?? '', 'slot' => $current['slot']];
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
    ];
}
```

- [ ] **Step 3b: Add the `draw` branch in `OverlayController::data()`** (before the `else` groups branch)

```php
} elseif ($type === 'draw') {
    $drawState = $state['draws'][$activeId] ?? [];
    if (empty($drawState)) {
        $drawState = app(\App\Services\DrawEngine::class)->init($window, []);
    }
    $payload['draw'] = $data->resolveDraw($window, $drawState);
```

> `$state` already merges defaults at the top of `data()`. Ensure `defaultState()` is tolerant of a missing `draws` key (it is — `$state['draws'] ?? []`).

- [ ] **Step 4: Run, verify pass.**

- [ ] **Step 5: Commit**

```bash
git add app/Services/OverlayData.php app/Http/Controllers/OverlayController.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat(draw): data endpoint draw branch payload"
```

---

## Task 9: Renderer — draw board branch, CSS, faster poll

**Files:**
- Modify: `resources/views/overlays/base.blade.php`
- Modify: `resources/views/overlays/window.blade.php`

No automated test (browser rendering); manual verification.

- [ ] **Step 1: Per-window poll interval** (`base.blade.php`)

After computing `d` in `tick()`, allow the draw window to poll faster. Change the fixed interval to a re-armed timeout:
```js
// replace `setInterval(tick, POLL_MS)` with a self-scheduling loop:
let pollTimer = null;
function schedule(d) {
    const ms = (d && d.window_type === 'draw') ? 1000 : POLL_MS;
    clearTimeout(pollTimer);
    pollTimer = setTimeout(loop, ms);
}
async function loop() { const d = await tick(); schedule(d); }
loop();
```
Make `tick()` `return d;` (the parsed payload) so `schedule` can read `window_type`. Keep the existing `tick()` body; just return `d` at the end (and on the not-visible early return, `return d;`). Remove the old `tick(); setInterval(tick, POLL_MS);` lines.

- [ ] **Step 2: Extend the change-signature** (`base.blade.php`, the `sig` object) — add the draw state so live draws refresh:
```js
dr: d.draw,
```

- [ ] **Step 3: Add draw CSS** (`window.blade.php`, in `@section('styles')`, after the Sponsors block)

```css
/* ── Draw (burtai) ───────────────────────────────────────── */
.draw-stage { position: fixed; inset: 0; padding: 28px 34px; display: flex; flex-direction: column; }
.draw-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.draw-head .left { display: flex; align-items: center; gap: 12px; }
.draw-head img { height: 46px; width: auto; object-fit: contain; }
.draw-head .tt { font-family: 'Oswald',sans-serif; font-weight: 700; font-size: 22px; color: var(--ov-text); line-height: 1.1; }
.draw-head .cat { font-size: 13px; color: var(--ov-muted); }
.draw-head .badge { font-family: 'Oswald',sans-serif; font-weight: 700; letter-spacing: .12em; font-size: 26px; color: var(--ov-accent); }
.draw-head .pot { font-family: 'Oswald',sans-serif; font-size: 12px; font-weight: 600; color: #0A0A0F; background: var(--ov-accent); padding: 3px 10px; border-radius: 6px; margin-left: 12px; }
.draw-body { flex: 1; display: flex; gap: 18px; min-height: 0; }
.draw-grid { flex: 1; display: grid; gap: 12px; align-content: start; }
.dg-card { background: var(--ov-bg); border: 1px solid rgba(127,127,127,.28); border-top: 3px solid var(--ov-accent); border-radius: 8px; padding: 8px 12px; }
.dg-card .gname { font-family: 'Oswald',sans-serif; font-weight: 600; letter-spacing: .1em; font-size: 14px; color: var(--ov-accent); margin-bottom: 6px; }
.dg-slot { display: flex; gap: 8px; font-size: 15px; padding: 5px 0; border-top: 1px solid rgba(127,127,127,.14); }
.dg-slot:first-of-type { border-top: 0; }
.dg-slot .pos { color: var(--ov-muted); width: 18px; }
.dg-slot.empty .nm { color: #5a5a66; }
.dg-slot.just-in { animation: drawIn .6s cubic-bezier(.16,1,.3,1) both; }
@keyframes drawIn { from { opacity: 0; transform: translateY(-8px); background: var(--ov-accent); } to { opacity: 1; transform: none; } }
/* bracket draw: first-round pairs */
.db-pairs { flex: 1; display: grid; gap: 10px; align-content: start; }
.db-pair { background: var(--ov-bg); border: 1px solid rgba(127,127,127,.28); border-left: 3px solid var(--ov-accent); border-radius: 6px; }
.db-pair .dg-slot { padding: 7px 12px; }
/* remaining pool */
.draw-pool { width: 240px; flex: none; }
.draw-pool .lbl { font-family: 'Oswald',sans-serif; text-transform: uppercase; letter-spacing: .08em; font-size: 11px; color: var(--ov-muted); margin-bottom: 8px; }
.draw-pool .chips { display: flex; flex-wrap: wrap; gap: 6px; }
.draw-pool .chip { font-size: 12px; background: rgba(127,127,127,.16); padding: 4px 9px; border-radius: 12px; color: var(--ov-text); }
/* centre reveal roulette */
.draw-reveal { position: fixed; left: 50%; top: 56%; transform: translate(-50%,-50%); background: var(--ov-accent); color: #0A0A0F; padding: 12px 26px; border-radius: 10px; text-align: center; box-shadow: 0 20px 50px -18px rgba(0,0,0,.7); }
.draw-reveal .k { font-family: 'Oswald',sans-serif; font-weight: 600; letter-spacing: .14em; font-size: 11px; opacity: .7; }
.draw-reveal .nm { font-family: 'Barlow',sans-serif; font-weight: 700; font-size: 22px; margin-top: 3px; }
.draw-reveal .to { font-size: 12px; margin-top: 2px; }
/* sponsor strip; avoids camera corner via body class */
.draw-spons { display: flex; align-items: center; gap: 14px; margin-top: 14px; }
.draw-spons img { height: 30px; width: auto; object-fit: contain; opacity: .9; }
.draw-done { color: var(--ov-accent); font-family: 'Oswald',sans-serif; font-weight: 700; letter-spacing: .14em; }
```

- [ ] **Step 4: Add the draw render branch** (`window.blade.php`, in `@section('render_fn_body')`, after the schedule branch's `return;` and before the groups section)

```js
// ── Draw (burtai) ───────────────────────────────────────────
if ((d.window_type || 'groups') === 'draw') {
    const dr = d.draw || {};
    const slots = dr.slots || {};
    const nameAt = (k) => (slots[k] && slots[k].name) || null;
    const curSlot = dr.current && dr.current.slot;

    const slotRow = (k, pos) => {
        const nm = nameAt(k);
        const justIn = (k === curSlot) ? ' just-in' : '';
        return `<div class="dg-slot ${nm ? '' : 'empty'}${justIn}"><span class="pos">${pos}</span><span class="nm">${nm || '—'}</span></div>`;
    };

    let bodyHtml = '';
    if (dr.format === 'bracket') {
        bodyHtml = '<div class="db-pairs">';
        for (const pair of (dr.board || [])) {
            bodyHtml += `<div class="db-pair">${slotRow(pair[0], '')}${slotRow(pair[1], '')}</div>`;
        }
        bodyHtml += '</div>';
    } else {
        const groups = dr.board || [];
        const cols = groups.length <= 2 ? groups.length : (groups.length <= 6 ? 3 : 4);
        bodyHtml = `<div class="draw-grid" style="grid-template-columns:repeat(${cols||1},1fr)">`;
        for (const g of groups) {
            bodyHtml += `<div class="dg-card"><div class="gname">Grupė ${g.label}</div>`;
            g.slots.forEach((k, i) => { bodyHtml += slotRow(k, (i + 1) + '.'); });
            bodyHtml += '</div>';
        }
        bodyHtml += '</div>';
    }

    const pool = (dr.pool || []).map((t) => `<span class="chip">${t.name}</span>`).join('');
    const poolHtml = `<div class="draw-pool"><div class="lbl">Liko traukti (${(dr.pool||[]).length})</div><div class="chips">${pool}</div></div>`;

    const logo = dr.show_tournament && d.logo ? `<img src="${d.logo}" alt="">` : '';
    const tt = dr.show_tournament ? (d.tournament_title || d.title || '') : '';
    const headHtml = `<div class="draw-head"><div class="left">${logo}<div><div class="tt">${tt}</div><div class="cat">Burtai</div></div></div>`
        + `<div><span class="badge">BURTAI</span>${dr.status !== 'done' ? `<span class="pot">Krepšelis ${dr.active_pot}</span>` : '<span class="draw-done">Baigta</span>'}</div></div>`;

    const sponsors = (dr.sponsors || []).map((s) => `<img src="${s.logo}" alt="">`).join('');
    const sponsHtml = sponsors ? `<div class="draw-spons">${sponsors}</div>` : '';

    stage.innerHTML = `<div class="draw-stage draw-corner-${dr.camera_corner||'bottom-right'}">${headHtml}<div class="draw-body">${bodyHtml}${poolHtml}</div>${sponsHtml}</div>`;

    // Reveal roulette: cycle remaining names ~2s then land on current.
    clearInterval(window.__drawRoulette);
    const reveal = document.getElementById('draw-reveal-host') || (() => {
        const h = document.createElement('div'); h.id = 'draw-reveal-host'; document.body.appendChild(h); return h;
    })();
    const cur = dr.current;
    const prev = window.__drawLastSlot;
    if (cur && cur.slot !== prev) {
        window.__drawLastSlot = cur.slot;
        const names = (dr.pool || []).map((t) => t.name).concat([cur.name]);
        let i = 0, ticks = 0;
        reveal.innerHTML = `<div class="draw-reveal"><div class="k">TRAUKIAMA…</div><div class="nm" id="rl-nm">${cur.name}</div><div class="to" id="rl-to"></div></div>`;
        const nmEl = document.getElementById('rl-nm'), toEl = document.getElementById('rl-to');
        window.__drawRoulette = setInterval(() => {
            ticks++;
            if (ticks < 16 && names.length > 1) { nmEl.textContent = names[i % names.length]; i++; }
            else {
                clearInterval(window.__drawRoulette);
                nmEl.textContent = cur.name;
                toEl.textContent = '→ ' + cur.slot;
                setTimeout(() => { reveal.innerHTML = ''; }, 1800);
            }
        }, 110);
    } else if (!cur) {
        reveal.innerHTML = '';
    }
    return;
}
```

- [ ] **Step 5: Add the not-visible cleanup** (`base.blade.php`, the `!d.visible` branch and the draw window-switch) — remove the reveal host like the ticker:
```js
const rv = document.getElementById('draw-reveal-host'); if (rv) rv.remove();
```
Add the same line in `window.blade.php` at the very top of `render_fn_body` (next to the existing `ov-ticker` cleanup) so switching away from a draw clears the reveal.

- [ ] **Step 6: Camera-corner cutout** — the transparent corner is achieved by simply not drawing there; the `.draw-corner-*` class can shrink the body/sponsor area away from the chosen corner. Minimal v1: add CSS so the sponsor strip and pool avoid the bottom corners:
```css
.draw-corner-bottom-right .draw-spons { padding-right: 32%; }
.draw-corner-bottom-left  .draw-spons { padding-left: 32%; }
.draw-corner-bottom-right .draw-pool,
.draw-corner-bottom-left  .draw-pool { margin-bottom: 22%; }
```

- [ ] **Step 7: Manual verify** — see Task 11.

- [ ] **Step 8: Commit**

```bash
git add resources/views/overlays/base.blade.php resources/views/overlays/window.blade.php
git commit -m "feat(draw): on-screen draw board, reveal roulette, faster poll"
```

---

## Task 10: Operator console — DrawControlPage

**Files:**
- Create: `app/Filament/Pages/DrawControlPage.php`
- Create: `resources/views/filament/pages/draw-control.blade.php`
- Test: `tests/Feature/DrawControlTest.php`

The page mutates `overlay.state['draws'][$windowId]` via the `DrawEngine`. Test the Livewire actions with Filament's testing helpers.

- [ ] **Step 1: Write failing feature test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\DrawControlPage;
use App\Models\Overlay;
use App\Models\OverlaySnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DrawControlTest extends TestCase
{
    use RefreshDatabase;

    private function overlayWithDrawWindow(): Overlay
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'participants_by_category' => ['53636' => [
                ['id' => 1, 'name' => 'A / B', 'pot' => 1],
                ['id' => 2, 'name' => 'C / D', 'pot' => 1],
            ]],
        ]]);

        return Overlay::create([
            'name' => 'D', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [[
                'id' => 'w1', 'type' => 'draw', 'name' => 'Traukimas', 'category_id' => 53636,
                'format' => 'groups', 'group_count' => 2, 'group_size' => 1, 'use_pots' => true,
            ]],
            'state' => ['active_window_id' => null, 'next_match' => ''],
        ]);
    }

    public function test_load_participants_freezes_pool_into_state(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('loadParticipants');

        $overlay->refresh();
        $this->assertCount(2, $overlay->state['draws']['w1']['teams']);
        $this->assertSame('A / B', $overlay->state['draws']['w1']['teams'][0]['name']);
    }

    public function test_draw_places_a_team_and_undo_removes_it(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        $comp = Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('loadParticipants')
            ->call('drawNext');

        $overlay->refresh();
        $placed = array_filter($overlay->state['draws']['w1']['slots'], fn ($t) => $t !== null);
        $this->assertCount(1, $placed);

        $comp->call('undo');
        $overlay->refresh();
        $placed = array_filter($overlay->state['draws']['w1']['slots'], fn ($t) => $t !== null);
        $this->assertCount(0, $placed);
    }

    public function test_play_sets_active_window(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('play');

        $this->assertSame('w1', $overlay->fresh()->state['active_window_id']);
    }
}
```

- [ ] **Step 2: Run, verify fail** — `php artisan test --filter=DrawControlTest` → FAIL (page missing).

- [ ] **Step 3: Implement `DrawControlPage`**

```php
<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use App\Services\DrawEngine;
use App\Services\OverlayData;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DrawControlPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Traukimo valdymas';
    protected static ?string $title = 'Traukimo valdymas';
    protected static string $view = 'filament.pages.draw-control';

    public ?int $overlayId = null;
    public ?string $windowId = null;
    public string $search = '';
    public ?string $manualSlot = null;

    public function selectedOverlay(): ?Overlay
    {
        return $this->overlayId ? Overlay::find($this->overlayId) : null;
    }

    /** @return array<int,string> */
    public function overlayOptions(): array
    {
        return Overlay::orderBy('name')->pluck('name', 'id')->all();
    }

    /** Draw-type windows of the selected overlay. @return array<string,string> */
    public function windowOptions(): array
    {
        $out = [];
        foreach ($this->selectedOverlay()?->windows ?? [] as $w) {
            if (($w['type'] ?? null) === 'draw') {
                $out[$w['id']] = $w['name'] ?? $w['id'];
            }
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function currentWindow(): ?array
    {
        foreach ($this->selectedOverlay()?->windows ?? [] as $w) {
            if (($w['id'] ?? null) === $this->windowId) {
                return $w;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function drawState(): array
    {
        return $this->selectedOverlay()?->state['draws'][$this->windowId] ?? [];
    }

    private function saveDrawState(array $drawState): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['draws'][$this->windowId] = $drawState;
        $overlay->state = $state;
        $overlay->save();
    }

    public function loadParticipants(): void
    {
        $window = $this->currentWindow();
        if (! $window) {
            return;
        }
        $teams = app(OverlayData::class)->participants(
            (string) $this->selectedOverlay()->tournament_external_id,
            (int) ($window['category_id'] ?? 0),
        );
        $state = app(DrawEngine::class)->init($window, $teams);
        $this->saveDrawState($state);

        Notification::make()->title('Dalyviai užkrauti (' . count($teams) . ')')->success()->send();
    }

    private function run(callable $fn): void
    {
        $window = $this->currentWindow();
        if (! $window) {
            return;
        }
        try {
            $this->saveDrawState($fn(app(DrawEngine::class), $window, $this->drawState()));
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function drawNext(): void
    {
        $this->run(fn (DrawEngine $e, $w, $s) => $e->drawNext($w, $s));
    }

    public function placeManual(int $teamId): void
    {
        if (! $this->manualSlot) {
            Notification::make()->title('Pirma pasirink vietą.')->warning()->send();

            return;
        }
        $slot = $this->manualSlot;
        $this->run(fn (DrawEngine $e, $w, $s) => $e->place($w, $s, $teamId, $slot));
        $this->manualSlot = null;
    }

    public function undo(): void
    {
        $this->run(fn (DrawEngine $e, $w, $s) => $e->undo($w, $s));
    }

    public function reset(): void
    {
        $this->run(fn (DrawEngine $e, $w, $s) => $e->reset($w, $s));
    }

    public function play(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['active_window_id'] = $this->windowId;
        $overlay->state = $state;
        $overlay->save();
        Notification::make()->title('▶ Rodoma')->success()->send();
    }

    public function stop(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['active_window_id'] = null;
        $overlay->state = $state;
        $overlay->save();
        Notification::make()->title('■ Sustabdyta')->send();
    }

    /** Remaining (unplaced) teams, filtered by search. @return list<array> */
    public function remainingTeams(): array
    {
        $s = $this->drawState();
        $placed = array_values(array_filter($s['slots'] ?? [], fn ($t) => $t !== null));
        $teams = array_filter(
            $s['teams'] ?? [],
            fn ($t) => ! in_array($t['id'], $placed, true)
                && ($this->search === '' || stripos($t['name'] ?? '', $this->search) !== false),
        );

        return array_values($teams);
    }

    /** Empty slot keys for the manual-place picker. @return list<string> */
    public function emptySlots(): array
    {
        $s = $this->drawState();

        return array_values(array_keys(array_filter($s['slots'] ?? [], fn ($t) => $t === null)));
    }
}
```

- [ ] **Step 4: Implement the view** `resources/views/filament/pages/draw-control.blade.php`

Model it on `overlay-control.blade.php`. Required controls (use `wire:model.live` for `overlayId`, `windowId`, `search`, `manualSlot`):
- Overlay `<select wire:model.live="overlayId">` from `$this->overlayOptions()`.
- Window `<select wire:model.live="windowId">` from `$this->windowOptions()`.
- Buttons: `wire:click="loadParticipants"`, `wire:click="drawNext"` (big), `wire:click="undo"`, `wire:click="reset"`, `wire:click="play"`, `wire:click="stop"`.
- Pot indicator: `@php $s = $this->drawState(); @endphp` → show `$s['active_pot']` and `$s['status']`.
- Manual place: `<input wire:model.live="search">`, a `<select wire:model.live="manualSlot">` over `$this->emptySlots()`, and a list of `$this->remainingTeams()` each with `<button wire:click="placeManual({{ $t['id'] }})">`.
- Mini board preview: iterate `$this->drawState()['slots']` showing key → team name (look up name in `$s['teams']`).

- [ ] **Step 5: Run, verify pass** — `php artisan test --filter=DrawControlTest` green.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/DrawControlPage.php resources/views/filament/pages/draw-control.blade.php tests/Feature/DrawControlTest.php
git commit -m "feat(draw): operator console (DrawControlPage)"
```

---

## Task 11: End-to-end manual verification & docs

**Files:**
- Modify: `docs/overlays.md`

- [ ] **Step 1: Full suite** — `php artisan test` → all green (existing 20 + new ones).

- [ ] **Step 2: Verify push.js participants** — run `node tools/overlay-push/push.js` against a tournament with a category; confirm the log and that `OverlaySnapshot.payload['participants_by_category']` is populated (tinker or DB).

- [ ] **Step 3: Live walk-through** — create an overlay, add a "Traukimas" window (groups 4×4, pots on), open Traukimo valdymas, Load participants, Draw a few, try Manual place, Undo, Reset, Play; open `/overlay/{token}` in a browser and confirm the board renders, the roulette reveals, and the chosen camera corner stays clear.

- [ ] **Step 4: Document** — add a "Traukimas (burtai)" section to `docs/overlays.md`: the window type, its config options, the participant pipeline, the Traukimo valdymas console, and the ~1s poll note.

- [ ] **Step 5: Commit**

```bash
git add docs/overlays.md
git commit -m "docs: draw ceremony overlay usage"
```

---

## Notes for the implementer

- **Run tests on Windows:** `php artisan test --filter=DrawEngineTest` (PowerShell). The suite uses SQLite `:memory:`.
- **ESM in push.js:** it is `"type":"module"` — no `require`/`module.exports`. End with the existing `loop();` pattern; do not add a `require.main` guard.
- **Tournated field names:** the `entries`/`seed` query in Task 6 is the one unknown — dump it against a real category first (a throwaway `gql(...)` call) and adjust before wiring it in.
- **No websockets:** the ~1s draw poll is intentional; the ~2s roulette masks the latency. Don't reach for broadcasting.
- **YAGNI:** v1 is single-operator, last-write-wins. No concurrency control, no export.
