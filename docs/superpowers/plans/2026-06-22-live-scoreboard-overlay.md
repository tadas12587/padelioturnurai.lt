# Live Scoreboard (REZULTATAS) Overlay — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `type:'score'` overlay: a compact, theme-coloured live padel scoreboard the operator drives with +/- buttons, with a pure `ScoreEngine` computing points/games/sets/tiebreaks and serve rotation.

**Architecture:** `ScoreEngine` (pure PHP, `(config,state)→state`) holds all padel rules (points, advantage/golden/STAR deuce, tiebreak, super-tiebreak, serve, undo). The live state lives in `overlay.state['score']`. A `Rezultatas` control page picks a fixture and drives +/-/serve; the `/data` endpoint's `score` branch builds the card payload (abbreviated names, per-team sets/games/point, serve); `window.blade` renders a compact themed card positioned/sized by config.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11, Blade + vanilla JS.

**Spec:** `docs/superpowers/specs/2026-06-22-live-scoreboard-overlay-design.md`

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `app/Services/ScoreEngine.php` | All scoring rules + serve + undo | Create |
| `tests/Unit/ScoreEngineTest.php` | Engine unit tests | Create |
| `app/Services/OverlayData.php` | `resolveScore`, `abbrevName` | Modify |
| `app/Http/Controllers/OverlayController.php` | `data()` score branch | Modify |
| `app/Filament/Resources/OverlayResource.php` | `'score'` window type + config fields | Modify |
| `app/Filament/Pages/ScoreControlPage.php` (+ view) | Pick fixture, +/-, serve, undo, reset, play/stop | Create |
| `resources/views/overlays/window.blade.php` | `score` render branch + CSS | Modify |
| `resources/views/overlays/base.blade.php` | cleanup + signature | Modify |
| `tests/Feature/ScoreboardTest.php` | data + control feature tests | Create |

**Engine config** (normalised via `ScoreEngine::config($window)`): `games_per_set` (6), `tiebreak_at` (=games_per_set), `sets_to_win` (2), `tiebreak` (true), `tiebreak_to` (7), `super_tb` (true), `super_tb_to` (10), `deuce_mode` (`advantage|golden|star`, default `star`).

**State** (`state['score']`): `teams` (`[[p1,p2],[p3,p4]]`), `sets` (`[[6,4],…]`), `sets_won`, `games`, `points` (0..3), `adv` (team|null), `star_stage` (`0|adv1|1|adv2|star`), `tiebreak`, `super_tiebreak`, `tb`, `tb_start_server`, `server_team`, `server_player`, `next_player`, `status`, `winner`, `history`.

---

## Task 1: ScoreEngine core (points → game → set → match, serve, undo)

**Files:** Create `app/Services/ScoreEngine.php`, `tests/Unit/ScoreEngineTest.php`

- [ ] **Step 1: Failing tests**

```php
<?php

namespace Tests\Unit;

use App\Services\ScoreEngine;
use Tests\TestCase;

class ScoreEngineTest extends TestCase
{
    private ScoreEngine $e;
    private array $cfg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e = new ScoreEngine();
        // default config but golden deuce for simple, deterministic game wins
        $this->cfg = $this->e->config(['score_deuce_mode' => 'golden', 'score_sets_to_win' => 1, 'score_tiebreak' => false]);
    }

    private function play(array $state, array $seq): array
    {
        foreach ($seq as $team) {
            $state = $this->e->point($this->cfg, $state, $team);
        }

        return $state;
    }

    public function test_points_progress_and_win_a_game(): void
    {
        $s = $this->e->init($this->cfg, [['A B'], ['C D']]);
        $s = $this->play($s, [0, 0, 0]); // 40-0
        $this->assertSame([3, 0], $s['points']);
        $s = $this->play($s, [0]);       // game
        $this->assertSame([1, 0], $s['games']);
        $this->assertSame([0, 0], $s['points']);
    }

    public function test_win_a_set_by_two_and_finish_single_set_match(): void
    {
        $s = $this->e->init($this->cfg, [['A B'], ['C D']]);
        // team 0 wins 6 games to 0 (4 points each)
        for ($g = 0; $g < 6; $g++) {
            $s = $this->play($s, [0, 0, 0, 0]);
        }
        $this->assertSame('finished', $s['status']);
        $this->assertSame(0, $s['winner']);
        $this->assertSame([[6, 0]], $s['sets']);
    }

    public function test_serve_alternates_each_game(): void
    {
        $s = $this->e->init($this->cfg, [['A B'], ['C D']]);
        $this->assertSame(0, $s['server_team']);          // team 0 serves game 1
        $s = $this->play($s, [0, 0, 0, 0]);               // game 1 done
        $this->assertSame(1, $s['server_team']);          // team 1 serves game 2
    }

    public function test_undo_reverts_last_point(): void
    {
        $s = $this->e->init($this->cfg, [['A B'], ['C D']]);
        $s = $this->play($s, [0, 0]);
        $this->assertSame([2, 0], $s['points']);
        $s = $this->e->undo($this->cfg, $s);
        $this->assertSame([1, 0], $s['points']);
    }
}
```

- [ ] **Step 2: Run, verify fail** — `php artisan test --filter=ScoreEngineTest`.

- [ ] **Step 3: Implement core `ScoreEngine`**

```php
<?php

namespace App\Services;

class ScoreEngine
{
    /** Normalise window keys → engine config. */
    public function config(array $w): array
    {
        $gps = (int) ($w['score_games_per_set'] ?? 6);

        return [
            'games_per_set' => $gps,
            'tiebreak_at'   => (int) ($w['score_tiebreak_at'] ?? $gps),
            'sets_to_win'   => (int) ($w['score_sets_to_win'] ?? 2),
            'tiebreak'      => (bool) ($w['score_tiebreak'] ?? true),
            'tiebreak_to'   => (int) ($w['score_tiebreak_to'] ?? 7),
            'super_tb'      => (bool) ($w['score_super_tb'] ?? true),
            'super_tb_to'   => (int) ($w['score_super_tb_to'] ?? 10),
            'deuce_mode'    => $w['score_deuce_mode'] ?? 'star',
        ];
    }

    /** @param array $teams [[p1,p2],[p3,p4]] */
    public function init(array $config, array $teams): array
    {
        return [
            'teams' => array_values($teams),
            'sets' => [], 'sets_won' => [0, 0], 'games' => [0, 0], 'points' => [0, 0],
            'adv' => null, 'star_stage' => 0,
            'tiebreak' => false, 'super_tiebreak' => false, 'tb' => [0, 0], 'tb_start_server' => 0,
            'server_team' => 0, 'server_player' => 0, 'next_player' => [1, 0],
            'status' => 'playing', 'winner' => null, 'history' => [],
        ];
    }

    public function point(array $config, array $state, int $team): array
    {
        if ($state['status'] === 'finished') {
            return $state;
        }
        $state = $this->pushHistory($state);

        return $state['tiebreak']
            ? $this->tbPoint($config, $state, $team)
            : $this->gamePoint($config, $state, $team);
    }

    public function undo(array $config, array $state): array
    {
        if (empty($state['history'])) {
            return $state;
        }
        $history = $state['history'];
        $prev = array_pop($history);
        $prev['history'] = $history;

        return $prev;
    }

    public function reset(array $config, array $state): array
    {
        return $this->init($config, $state['teams']);
    }

    public function setServer(array $state, int $team, int $player = 0): array
    {
        $state['server_team'] = $team;
        $state['server_player'] = $player;
        $state['next_player'] = [$team === 0 ? 1 - $player : 0, $team === 1 ? 1 - $player : 0];

        return $state;
    }

    private function pushHistory(array $state): array
    {
        $snap = $state;
        unset($snap['history']);
        $state['history'][] = $snap;
        if (count($state['history']) > 400) {
            array_shift($state['history']);
        }

        return $state;
    }

    private function gamePoint(array $config, array $state, int $team): array
    {
        $p = $state['points'];
        $bothAt40 = $p[0] >= 3 && $p[1] >= 3;

        if (! $bothAt40) {
            if ($p[$team] < 3) {
                $state['points'][$team]++;

                return $state;
            }

            return $this->awardGame($config, $state, $team); // 40 vs <40 → game
        }

        // both at 40 — Task 2 replaces this with advantage/golden/star. For now: golden.
        return $this->awardGame($config, $state, $team);
    }

    private function awardGame(array $config, array $state, int $team): array
    {
        $state['games'][$team]++;
        $state['points'] = [0, 0];
        $state['adv'] = null;
        $state['star_stage'] = 0;
        $state = $this->rotateServer($state);

        return $this->checkSet($config, $state);
    }

    private function checkSet(array $config, array $state): array
    {
        $g = $state['games'];
        $gps = $config['games_per_set'];

        if ($g[0] >= $gps && $g[0] - $g[1] >= 2) {
            return $this->awardSet($config, $state, 0, [$g[0], $g[1]]);
        }
        if ($g[1] >= $gps && $g[1] - $g[0] >= 2) {
            return $this->awardSet($config, $state, 1, [$g[0], $g[1]]);
        }

        return $state;
    }

    private function awardSet(array $config, array $state, int $team, array $score): array
    {
        $state['sets'][] = $score;
        $state['sets_won'][$team]++;
        $state['games'] = [0, 0];
        $state['points'] = [0, 0];
        $state['adv'] = null;
        $state['star_stage'] = 0;
        $state['tiebreak'] = false;
        $state['tb'] = [0, 0];

        if ($state['sets_won'][$team] >= $config['sets_to_win']) {
            $state['status'] = 'finished';
            $state['winner'] = $team;

            return $state;
        }

        return $state;
    }

    private function rotateServer(array $state): array
    {
        $newTeam = 1 - $state['server_team'];
        $player = $state['next_player'][$newTeam];
        $state['server_team'] = $newTeam;
        $state['server_player'] = $player;
        $state['next_player'][$newTeam] = 1 - $player;

        return $state;
    }

    // tbPoint added in Task 3.
    private function tbPoint(array $config, array $state, int $team): array
    {
        return $state;
    }
}
```

- [ ] **Step 4: Run, verify pass.** Commit:

```bash
git add app/Services/ScoreEngine.php tests/Unit/ScoreEngineTest.php
git commit -m "feat(score): ScoreEngine core — points, game, set, match, serve, undo"
```

---

## Task 2: Deuce modes (advantage / golden / STAR)

**Files:** Modify `ScoreEngine.php`, `ScoreEngineTest.php`

- [ ] **Step 1: Failing tests**

```php
public function test_advantage_needs_two_points(): void
{
    $cfg = $this->e->config(['score_deuce_mode' => 'advantage', 'score_sets_to_win' => 1, 'score_tiebreak' => false]);
    $s = $this->e->init($cfg, [['A'], ['B']]);
    $s = $this->play($s, [0, 0, 0, 1, 1, 1]); // 40-40
    $s = $this->e->point($cfg, $s, 0);        // Ad team 0
    $this->assertSame(0, $s['adv']);
    $this->assertSame([0, 0], $s['games']);
    $s = $this->e->point($cfg, $s, 1);        // back to deuce
    $this->assertNull($s['adv']);
    $s = $this->e->point($cfg, $s, 0);        // Ad 0
    $s = $this->e->point($cfg, $s, 0);        // game
    $this->assertSame([1, 0], $s['games']);
}

public function test_golden_point_decides_at_deuce(): void
{
    $cfg = $this->e->config(['score_deuce_mode' => 'golden', 'score_sets_to_win' => 1, 'score_tiebreak' => false]);
    $s = $this->e->init($cfg, [['A'], ['B']]);
    $s = $this->play($s, [0, 0, 0, 1, 1, 1]); // 40-40
    $s = $this->e->point($cfg, $s, 1);        // one point → game to 1
    $this->assertSame([0, 1], $s['games']);
}

public function test_star_point_sequence(): void
{
    $cfg = $this->e->config(['score_deuce_mode' => 'star', 'score_sets_to_win' => 1, 'score_tiebreak' => false]);
    $s = $this->e->init($cfg, [['A'], ['B']]);
    $s = $this->play($s, [0, 0, 0, 1, 1, 1]);  // 40-40 (stage 0)
    $s = $this->e->point($cfg, $s, 0);         // first advantage → team 0
    $this->assertSame('adv1', $s['star_stage']);
    $s = $this->e->point($cfg, $s, 1);         // lost adv → back to deuce (stage 1)
    $this->assertSame(1, $s['star_stage']);
    $s = $this->e->point($cfg, $s, 1);         // second advantage → team 1
    $this->assertSame('adv2', $s['star_stage']);
    $s = $this->e->point($cfg, $s, 0);         // lost adv → star point
    $this->assertSame('star', $s['star_stage']);
    $s = $this->e->point($cfg, $s, 0);         // star point → game to 0
    $this->assertSame([1, 0], $s['games']);
}
```

- [ ] **Step 2: Run, verify fail** (advantage/star fail — core uses golden).

- [ ] **Step 3: Replace the both-at-40 block in `gamePoint`**

```php
        // both at 40 — deuce logic per mode
        $mode = $config['deuce_mode'];

        if ($mode === 'golden') {
            return $this->awardGame($config, $state, $team);
        }

        if ($mode === 'advantage') {
            if ($state['adv'] === null) {
                $state['adv'] = $team;

                return $state;
            }
            if ($state['adv'] === $team) {
                return $this->awardGame($config, $state, $team);
            }
            $state['adv'] = null; // lost advantage → deuce

            return $state;
        }

        // star
        $stage = $state['star_stage'];
        if ($stage === 0) {
            $state['adv'] = $team;
            $state['star_stage'] = 'adv1';

            return $state;
        }
        if ($stage === 'adv1') {
            if ($state['adv'] === $team) {
                return $this->awardGame($config, $state, $team);
            }
            $state['adv'] = null;
            $state['star_stage'] = 1;

            return $state;
        }
        if ($stage === 1) {
            $state['adv'] = $team;
            $state['star_stage'] = 'adv2';

            return $state;
        }
        if ($stage === 'adv2') {
            if ($state['adv'] === $team) {
                return $this->awardGame($config, $state, $team);
            }
            $state['adv'] = null;
            $state['star_stage'] = 'star';

            return $state;
        }

        return $this->awardGame($config, $state, $team); // star point
```

- [ ] **Step 4: Run, verify pass.** Commit `feat(score): advantage / golden / STAR deuce modes`.

---

## Task 3: Tiebreak (mažasis) + `tiebreak_at`

**Files:** Modify `ScoreEngine.php`, `ScoreEngineTest.php`

- [ ] **Step 1: Failing tests**

```php
public function test_tiebreak_triggers_and_is_won_by_two(): void
{
    $cfg = $this->e->config(['score_deuce_mode' => 'golden', 'score_sets_to_win' => 1, 'score_tiebreak' => true, 'score_tiebreak_to' => 7]);
    $s = $this->e->init($cfg, [['A'], ['B']]);
    // reach 6-6: alternate holding serve — just force games via golden points
    $winGame = function ($s, $t) use ($cfg) {
        foreach ([$t, $t, $t, $t] as $x) {
            $s = $this->e->point($cfg, $s, $x);
        }

        return $s;
    };
    for ($i = 0; $i < 6; $i++) { $s = $winGame($s, 0); $s = $winGame($s, 1); }
    $this->assertTrue($s['tiebreak']);
    // team 0 wins tiebreak 7-1
    for ($i = 0; $i < 7; $i++) { $s = $this->e->point($cfg, $s, 0); }
    $this->assertSame('finished', $s['status']);
    $this->assertSame(0, $s['winner']);
    $this->assertSame([[7, 6]], $s['sets']);
}

public function test_tiebreak_at_eight_for_to_nine_set(): void
{
    $cfg = $this->e->config(['score_games_per_set' => 9, 'score_tiebreak_at' => 8, 'score_sets_to_win' => 1, 'score_deuce_mode' => 'golden']);
    $s = $this->e->init($cfg, [['A'], ['B']]);
    $winGame = function ($s, $t) use ($cfg) {
        foreach ([$t, $t, $t, $t] as $x) { $s = $this->e->point($cfg, $s, $x); }

        return $s;
    };
    for ($i = 0; $i < 8; $i++) { $s = $winGame($s, 0); $s = $winGame($s, 1); }
    $this->assertTrue($s['tiebreak']); // 8-8 → tiebreak
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Add tiebreak.** In `checkSet`, before `return $state;`, add the trigger:

```php
        if ($config['tiebreak'] && $g[0] >= $config['tiebreak_at'] && $g[1] >= $config['tiebreak_at']) {
            $state['tiebreak'] = true;
            $state['tb'] = [0, 0];
            $state['tb_start_server'] = $state['server_team'];
        }

        return $state;
```

Replace the stub `tbPoint` and add helpers:

```php
    private function tbPoint(array $config, array $state, int $team): array
    {
        $state['tb'][$team]++;
        $target = $state['super_tiebreak'] ? $config['super_tb_to'] : $config['tiebreak_to'];

        // serve indicator: A serves point 1, then switch every 2 points
        $k = $state['tb'][0] + $state['tb'][1] + 1; // next point number
        $state['server_team'] = ($state['tb_start_server'] + intdiv($k, 2)) % 2;

        $t = $state['tb'];
        if ($t[$team] >= $target && $t[$team] - $t[1 - $team] >= 2) {
            return $this->awardTiebreak($config, $state, $team);
        }

        return $state;
    }

    private function awardTiebreak(array $config, array $state, int $team): array
    {
        if ($state['super_tiebreak']) {
            $state['sets'][] = [$state['tb'][0], $state['tb'][1]];
            $state['sets_won'][$team]++;
            $state['status'] = 'finished';
            $state['winner'] = $team;

            return $state;
        }
        $wa = $config['tiebreak_at'];
        $score = $team === 0 ? [$wa + 1, $wa] : [$wa, $wa + 1];

        return $this->awardSet($config, $state, $team, $score);
    }
```

- [ ] **Step 4: Run, verify pass.** Commit `feat(score): tiebreak with configurable trigger and target`.

---

## Task 4: Super-tiebreak deciding set

**Files:** Modify `ScoreEngine.php`, `ScoreEngineTest.php`

- [ ] **Step 1: Failing test**

```php
public function test_super_tiebreak_decides_best_of_three(): void
{
    $cfg = $this->e->config(['score_deuce_mode' => 'golden', 'score_sets_to_win' => 2, 'score_super_tb' => true, 'score_super_tb_to' => 10]);
    $s = $this->e->init($cfg, [['A'], ['B']]);
    $winSet = function ($s, $t) use ($cfg) {
        for ($g = 0; $g < 6; $g++) { foreach ([$t, $t, $t, $t] as $x) { $s = $this->e->point($cfg, $s, $x); } }

        return $s;
    };
    $s = $winSet($s, 0); // 1-0 sets
    $s = $winSet($s, 1); // 1-1 → deciding set = super tiebreak
    $this->assertTrue($s['tiebreak']);
    $this->assertTrue($s['super_tiebreak']);
    for ($i = 0; $i < 10; $i++) { $s = $this->e->point($cfg, $s, 0); }
    $this->assertSame('finished', $s['status']);
    $this->assertSame(0, $s['winner']);
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Start the super-tiebreak when the deciding set begins.** In `awardSet`, before `return $state;` (the non-finish path), add:

```php
        return $this->maybeStartSuperTb($config, $state);
```

And add the helper:

```php
    private function maybeStartSuperTb(array $config, array $state): array
    {
        $need = $config['sets_to_win'] - 1;
        if ($config['super_tb'] && $state['sets_won'][0] === $need && $state['sets_won'][1] === $need) {
            $state['super_tiebreak'] = true;
            $state['tiebreak'] = true;
            $state['tb'] = [0, 0];
            $state['tb_start_server'] = $state['server_team'];
        }

        return $state;
    }
```

(Also call `maybeStartSuperTb` at the end of `awardTiebreak`'s non-super set path — replace its `return $this->awardSet(...)` so the deciding set after a tiebreak-set can be a super-tiebreak. `awardSet` already calls it, so no change needed.)

- [ ] **Step 4: Run, verify pass.** Commit `feat(score): super-tiebreak deciding set`.

---

## Task 5: OverlayData — resolveScore + abbreviated names

**Files:** Modify `OverlayData.php`; Test `tests/Feature/ScoreboardTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature;

use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_abbrev_name(): void
    {
        $d = app(OverlayData::class);
        $this->assertSame('T. Šeškauskas', $d->abbrevName('Tadas Šeškauskas'));
        $this->assertSame('J. Grigorenko', $d->abbrevName('Jevgenij Grigorenko'));
    }

    public function test_resolve_score_builds_card(): void
    {
        $d = app(OverlayData::class);
        $window = ['score_position' => 'top-left', 'score_width' => 520, 'show_level' => true];
        $state = [
            'teams' => [['Tadas Šeškauskas', 'Jonas Petraitis'], ['Adam Kowalski', 'Marius Šernius']],
            'sets' => [[6, 4]], 'sets_won' => [1, 0], 'games' => [3, 2], 'points' => [3, 1],
            'adv' => null, 'star_stage' => 0, 'tiebreak' => false, 'super_tiebreak' => false, 'tb' => [0, 0],
            'server_team' => 0, 'status' => 'playing', 'winner' => null,
        ];

        $card = $d->resolveScore($window, $state, ['category' => 'Vyrai Master', 'court' => 'Kortas 1', 'round' => 'Pusfinalis'], $d->scoreConfig($window));

        $this->assertSame('T. Šeškauskas / J. Petraitis', $card['teams'][0]['name']);
        $this->assertSame([6], $card['teams'][0]['sets']);
        $this->assertSame(3, $card['teams'][0]['games']);
        $this->assertSame('40', $card['teams'][0]['point']);
        $this->assertSame('15', $card['teams'][1]['point']);
        $this->assertTrue($card['teams'][0]['serving']);
        $this->assertSame('Vyrai Master', $card['level']);
    }
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement in `OverlayData.php`**

```php
public function abbrevName(string $full): string
{
    $full = trim(preg_replace('/\s+/', ' ', $full));
    if ($full === '') {
        return '';
    }
    $parts = explode(' ', $full);
    if (count($parts) === 1) {
        return $parts[0];
    }
    $first = array_shift($parts);

    return mb_strtoupper(mb_substr($first, 0, 1)) . '. ' . implode(' ', $parts);
}

/** @return array<string,mixed> */
public function scoreConfig(array $window): array
{
    return app(\App\Services\ScoreEngine::class)->config($window);
}

/**
 * @param  array<string,mixed>  $window
 * @param  array<string,mixed>  $state
 * @param  array<string,mixed>  $match   category/court/round context
 * @return array<string,mixed>
 */
public function resolveScore(array $window, array $state, array $match, array $config): array
{
    if (empty($state['teams'])) {
        return ['found' => false];
    }

    $pointLabels = ['0', '15', '30', '40'];
    $mode = $config['deuce_mode'] ?? 'star';
    $bothAt40 = ($state['points'][0] ?? 0) >= 3 && ($state['points'][1] ?? 0) >= 3;

    $pointFor = function (int $t) use ($state, $pointLabels, $mode, $bothAt40) {
        if (! empty($state['tiebreak'])) {
            return (string) ($state['tb'][$t] ?? 0);
        }
        if (($state['star_stage'] ?? 0) === 'star' || ($mode === 'golden' && $bothAt40 && $state['adv'] === null)) {
            return '★';
        }
        if ($state['adv'] === $t) {
            return 'AD';
        }
        if ($state['adv'] === (1 - $t)) {
            return '40';
        }

        return $pointLabels[min((int) ($state['points'][$t] ?? 0), 3)];
    };

    $team = function (int $t) use ($state, $pointFor) {
        $players = array_map(fn ($n) => $this->abbrevName((string) $n), $state['teams'][$t] ?? []);

        return [
            'name'    => implode(' / ', $players),
            'sets'    => array_map(fn ($s) => $s[$t], $state['sets'] ?? []),
            'games'   => (int) ($state['games'][$t] ?? 0),
            'point'   => $pointFor($t),
            'serving' => (int) ($state['server_team'] ?? 0) === $t,
            'winner'  => ($state['winner'] ?? null) === $t,
        ];
    };

    return [
        'found'    => true,
        'teams'    => [$team(0), $team(1)],
        'level'    => ($window['show_level'] ?? true) ? ($match['category'] ?? null) : null,
        'court'    => $match['court'] ?? null,
        'round'    => $match['round'] ?? null,
        'tiebreak' => ! empty($state['tiebreak']),
        'status'   => $state['status'] ?? 'playing',
        'position' => $window['score_position'] ?? 'top-left',
        'width'    => (int) ($window['score_width'] ?? 520),
    ];
}
```

- [ ] **Step 4: Run, verify pass.** Commit `feat(score): resolveScore payload + abbreviated names`.

---

## Task 6: Controller score branch + window fields

**Files:** Modify `OverlayController.php`, `OverlayResource.php`; Test `ScoreboardTest.php`

- [ ] **Step 1: Failing feature test**

```php
public function test_data_returns_score_card(): void
{
    \App\Models\OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
        'matches' => [['id' => 7, 'category' => 'Vyrai Master', 'court' => 'Kortas 1', 'round' => 'Finalas',
            'team1' => ['Tadas Šeškauskas', 'Jonas Petraitis'], 'team2' => ['Adam Kowalski', 'Marius Šernius']]],
    ]]);
    $overlay = \App\Models\Overlay::create([
        'name' => 'S', 'type' => 'group_standings', 'tournament_external_id' => '10424',
        'windows' => [['id' => 'w1', 'type' => 'score', 'name' => 'Rezultatas', 'score_deuce_mode' => 'star']],
        'state' => ['active_window_id' => 'w1', 'next_match' => '', 'score_match_id' => 7,
            'score' => ['teams' => [['Tadas Šeškauskas', 'Jonas Petraitis'], ['Adam Kowalski', 'Marius Šernius']],
                'sets' => [], 'sets_won' => [0, 0], 'games' => [1, 0], 'points' => [2, 0], 'adv' => null, 'star_stage' => 0,
                'tiebreak' => false, 'super_tiebreak' => false, 'tb' => [0, 0], 'server_team' => 0, 'status' => 'playing', 'winner' => null]],
    ]);

    $this->getJson("/overlay/{$overlay->token}/data")
        ->assertOk()
        ->assertJson(['visible' => true, 'window_type' => 'score'])
        ->assertJsonPath('score.found', true)
        ->assertJsonPath('score.teams.0.name', 'T. Šeškauskas / J. Petraitis')
        ->assertJsonPath('score.level', 'Vyrai Master');
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3a: Controller branch** (before `else` groups branch):

```php
} elseif ($type === 'score') {
    $scoreState = $state['score'] ?? [];
    $matchId = $state['score_match_id'] ?? null;
    $m = collect($data->matches((string) $overlay->tournament_external_id))
        ->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $matchId) ?? [];
    $payload['score'] = $data->resolveScore($window, $scoreState, $m, $data->scoreConfig($window));
```

- [ ] **Step 3b: OverlayResource** — add `'score' => 'Rezultatas'` to the window type options, and these fields gated `visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score')`:

```php
TextInput::make('score_games_per_set')->label('Geimų sete')->numeric()->default(6)->minValue(1),
TextInput::make('score_tiebreak_at')->label('Tiebreak prie (geimų)')->numeric()->default(6)
    ->helperText('Kai abi komandos pasiekia tiek geimų — tiebreak. „iki 6"→6, „iki 9"→8.'),
TextInput::make('score_sets_to_win')->label('Laimėtų setų (mačui)')->numeric()->default(2)->minValue(1),
Toggle::make('score_tiebreak')->label('Tiebreak sete')->default(true),
TextInput::make('score_tiebreak_to')->label('Tiebreak iki')->numeric()->default(7),
Toggle::make('score_super_tb')->label('Lemiamas setas – super tiebreak')->default(true),
TextInput::make('score_super_tb_to')->label('Super tiebreak iki')->numeric()->default(10),
Select::make('score_deuce_mode')->label('Lygiosios (40–40)')
    ->options(['advantage' => 'Pranašumas', 'golden' => 'Auksinis taškas', 'star' => 'STAR'])->default('star'),
Select::make('score_position')->label('Pozicija')
    ->options(['top-left' => 'Viršus — kairė', 'top-center' => 'Viršus — centras', 'top-right' => 'Viršus — dešinė',
        'bottom-left' => 'Apačia — kairė', 'bottom-center' => 'Apačia — centras', 'bottom-right' => 'Apačia — dešinė'])
    ->default('top-left'),
TextInput::make('score_width')->label('Plotis (px)')->numeric()->default(520),
Toggle::make('show_level')->label('Rodyti lygį / kategoriją')->default(true),
```

(All gated with the `score` visibility closure.)

- [ ] **Step 4: Run, verify pass.** Commit `feat(score): data endpoint branch + window config fields`.

---

## Task 7: Rezultatas control page

**Files:** Create `app/Filament/Pages/ScoreControlPage.php` + `resources/views/filament/pages/score-control.blade.php`; Test `ScoreboardTest.php`

Model on `H2hControlPage` (pick overlay → score window → fixture) plus the scoring buttons.

- [ ] **Step 1: Failing test**

```php
public function test_point_and_undo_via_control(): void
{
    \App\Models\OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
        'matches' => [['id' => 7, 'team1' => ['A B'], 'team2' => ['C D'], 'category' => 'X']],
    ]]);
    $overlay = \App\Models\Overlay::create([
        'name' => 'S', 'type' => 'group_standings', 'tournament_external_id' => '10424',
        'windows' => [['id' => 'w1', 'type' => 'score', 'name' => 'Rezultatas', 'score_deuce_mode' => 'star']],
        'state' => ['active_window_id' => null, 'next_match' => ''],
    ]);

    $c = \Livewire\Livewire::test(\App\Filament\Pages\ScoreControlPage::class)
        ->set('overlayId', $overlay->id)->set('windowId', 'w1')
        ->call('selectMatch', 7)   // loads teams + sets active window
        ->call('point', 0);

    $this->assertSame([1, 0], $overlay->fresh()->state['score']['points']);

    $c->call('undo');
    $this->assertSame([0, 0], $overlay->fresh()->state['score']['points']);
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement `ScoreControlPage`.** Properties `overlayId`, `windowId`, `search`. Methods:
  - `windowOptions()` — `type==='score'` windows.
  - `matches()` — from `OverlayData::matches`, filtered by `search` (team names), each with id/team1/team2/time/court/category.
  - `currentWindow()`, `scoreState()`, `saveScore($state)` (writes `state['score']`).
  - `selectMatch($id)`: find match; `state['score'] = ScoreEngine::init(config, [team1players, team2players])`; `state['score_match_id']=$id`; `state['active_window_id']=$windowId`; save. (teams = the match's `team1`/`team2` arrays.)
  - `point($team)`: `state['score'] = ScoreEngine::point(config, scoreState, $team)`; save. Config via `ScoreEngine::config($window)`.
  - `undo()`, `resetScore()`, `setServer($team)`, `stop()`.
  - View: overlay + window selects, fixture list (`selectMatch`), then a scoring panel — big **+**/**−** per team, current mini score (games/points), serve toggle buttons, **Iš naujo**, **Sustabdyti**. Reuse `overlay-control` styling.

- [ ] **Step 4: Run, verify pass.** Commit `feat(score): Rezultatas control page`.

---

## Task 8: Renderer — compact themed card

**Files:** Modify `window.blade.php`, `base.blade.php`

No automated test; manual verify.

- [ ] **Step 1: base.blade** — add `sc2: d.score` to the `sig`; in the not-visible branch and the `window.blade` top cleanup, remove a `#ov-score` body host.

- [ ] **Step 2: score render branch** (`window.blade.php`, after the h2h branch). Render to a `<body>` host `#ov-score`. Build a compact card:
  - Root `.sco-card` with inline `width:${d.score.width}px` and a position class `sco-<position>`; colours from `var(--ov-*)`.
  - Header: `.sco-head` — level (`score.level`) left, `court · round` right.
  - Two rows `.sco-row`: serve dot (accent when `serving`), team `name`, each completed set number, current `games`, then `point` (bold, accent when tiebreak/★). Winner row gets an accent tint.
  - If `!score.found` → nothing (empty host).
  - Everything sized in `em` relative to a base `font-size` scaled from `width` (e.g., `font-size: calc(width/34)px`) so it stays responsive.

- [ ] **Step 3: CSS** — `.sco-card { position:fixed; background:var(--ov-bg); border:1px solid …; border-radius; box-shadow; overflow:hidden; }`; position classes `.sco-top-left{top:40px;left:40px}` … `.sco-bottom-center{bottom:40px;left:50%;transform:translateX(-50%)}` etc.; header `var(--ov-muted)`/accent; point cell `font-family:'Oswald'; color:var(--ov-text)`; serving dot `background:var(--ov-accent)`. Use `em` for inner sizes; set card `font-size` from JS (`width/32`).

- [ ] **Step 4: Manual verify** — see Task 9.

- [ ] **Step 5: Commit** `feat(score): compact themed scoreboard card renderer`.

---

## Task 9: E2E verify & docs

**Files:** Modify `docs/overlays.md`

- [ ] **Step 1: Full suite** — `php artisan test` → new Score tests green.
- [ ] **Step 2: Live walk-through** — create an overlay with a `Rezultatas` window (star, best-of-3, super-TB); in the control pick a fixture; press +/- a few times through a game, deuce (verify STAR sequence), a set, tiebreak; toggle serve; open `/overlay/{token}` and confirm the compact card renders in the chosen corner/width with theme colours, abbreviated names, serve dot, and level.
- [ ] **Step 3: Docs** — add a "Rezultatas / Live score (`score`)" subsection to `docs/overlays.md` (window type, rules/settings, control page, STAR rule, position/width, name format).
- [ ] **Step 4: Commit** `docs: live scoreboard overlay usage`.

---

## Notes for the implementer

- **No push.js change** — team player names come from `matches[].team1/team2`; the score is manual.
- **Config lives in the window**; `ScoreEngine::config()` normalises it; the control page and `resolveScore` both call it.
- **Body host** for the card (position:fixed), like the draw/H2H boards.
- **Undo** is a full-state history stack — cheap and exact across game/set boundaries.
- **YAGNI:** no websockets (1s poll), serve shown at team level, single operator.
