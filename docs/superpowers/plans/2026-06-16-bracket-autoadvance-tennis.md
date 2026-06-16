# Bracket Auto-Advance + Tennis Scores Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bracket matches use per-team set games (tennis scores); the server auto-advances winners into later rounds and fills the 3rd-place match from the semifinal losers; the overlay shows set columns with the winning team highlighted.

**Architecture:** `bracket_data.matches` gains `sets1`/`sets2` (replacing `score1`/`score2`). The operator fills round 1 + winners only; `OverlayController::buildBracket` groups matches into ordered rounds and derives later-round names from previous winners (3rd place from semifinal losers). The overlay renders set games as cells per team row.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11, Blade + vanilla JS.

**Spec:** `docs/superpowers/specs/2026-06-16-bracket-autoadvance-tennis-design.md`

> Deploy reminder: NEVER run artisan config:cache/route:cache/view:cache. No migrations.

## File Structure

- `app/Models/Overlay.php` — `bracketSkeleton` emits `sets1`/`sets2`
- `app/Http/Controllers/OverlayController.php` — `buildBracket` derives advancement + passes sets
- `resources/views/overlays/window.blade.php` — render set cells per team
- `app/Filament/Resources/OverlayResource.php` — builder sets fields + helper
- tests

---

## Task 1: skeleton uses sets fields

**Files:**
- Modify: `app/Models/Overlay.php`
- Test: `tests/Unit/BracketSkeletonTest.php`

- [ ] **Step 1: Update the test** — in `tests/Unit/BracketSkeletonTest.php`, in `test_skeleton_8`,
  replace `$this->assertSame('', $m[0]['team1']);` with:

```php
        $this->assertSame('', $m[0]['team1']);
        $this->assertArrayHasKey('sets1', $m[0]);
        $this->assertArrayHasKey('sets2', $m[0]);
        $this->assertArrayNotHasKey('score1', $m[0]);
```

- [ ] **Step 2: Run, verify FAIL** — `php artisan test --filter=BracketSkeletonTest`

- [ ] **Step 3: Update `bracketSkeleton`'s `$blank` closure** in `app/Models/Overlay.php`:

```php
        $blank = fn (string $round) => [
            'round' => $round, 'team1' => '', 'team2' => '', 'sets1' => '', 'sets2' => '', 'winner' => null,
        ];
```

(Only the `$blank` line changes — the rest of the method stays.)

- [ ] **Step 4: Run, verify PASS** — `php artisan test --filter=BracketSkeletonTest`

- [ ] **Step 5: Commit**

```bash
git add app/Models/Overlay.php tests/Unit/BracketSkeletonTest.php
git commit -m "feat: bracket skeleton uses per-team set fields"
```

---

## Task 2: buildBracket — auto-advance + sets

**Files:**
- Modify: `app/Http/Controllers/OverlayController.php`
- Test: `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Replace the `buildBracket` private method** with the version that carries sets,
  normalizes `winner` to int 1/2/null, and derives later rounds:

```php
    /**
     * Group the flat bracket match list into ordered rounds, auto-advancing
     * winners into later rounds and filling the 3rd-place match from the
     * semifinal losers. A non-empty stored team name overrides the derived one.
     *
     * @param  array<string,mixed>  $bracketData
     * @return array{rounds:list<array{title:string,matches:list<array<string,mixed>>}>,third:?array<string,mixed>}
     */
    private function buildBracket(array $bracketData): array
    {
        $rounds = [];
        $index = [];
        $third = null;

        $normalize = function (array $m): array {
            $w = $m['winner'] ?? null;
            return [
                'team1'  => $m['team1'] ?? '',
                'team2'  => $m['team2'] ?? '',
                'sets1'  => $m['sets1'] ?? '',
                'sets2'  => $m['sets2'] ?? '',
                'winner' => ($w == 1) ? 1 : (($w == 2) ? 2 : null),
            ];
        };

        foreach ($bracketData['matches'] ?? [] as $m) {
            $entry = $normalize($m);
            $title = $m['round'] ?? '';
            if ($title === 'Dėl 3 vietos') {
                $third = $entry;
                continue;
            }
            if (! isset($index[$title])) {
                $index[$title] = count($rounds);
                $rounds[] = ['title' => $title, 'matches' => []];
            }
            $rounds[$index[$title]]['matches'][] = $entry;
        }

        $winnerName = fn (array $m) => $m['winner'] === 1 ? $m['team1'] : ($m['winner'] === 2 ? $m['team2'] : '');
        $loserName  = fn (array $m) => $m['winner'] === 1 ? $m['team2'] : ($m['winner'] === 2 ? $m['team1'] : '');

        // Derive names forward: round r match i is fed by round r-1 matches 2i / 2i+1.
        for ($r = 1; $r < count($rounds); $r++) {
            foreach ($rounds[$r]['matches'] as $i => &$match) {
                $prev = $rounds[$r - 1]['matches'];
                if ($match['team1'] === '' && isset($prev[2 * $i])) {
                    $match['team1'] = $winnerName($prev[2 * $i]);
                }
                if ($match['team2'] === '' && isset($prev[2 * $i + 1])) {
                    $match['team2'] = $winnerName($prev[2 * $i + 1]);
                }
            }
            unset($match);
        }

        // 3rd place: the round before the final (semifinals) supplies the losers.
        if ($third !== null && count($rounds) >= 2) {
            $sf = $rounds[count($rounds) - 2]['matches'];
            if (($third['team1'] ?? '') === '' && isset($sf[0])) {
                $third['team1'] = $loserName($sf[0]);
            }
            if (($third['team2'] ?? '') === '' && isset($sf[1])) {
                $third['team2'] = $loserName($sf[1]);
            }
        }

        return ['rounds' => $rounds, 'third' => $third];
    }
```

- [ ] **Step 2: Replace the bracket test** in `tests/Feature/OverlayEndpointTest.php` — remove
  `test_data_returns_bracket_grouped_with_third_place` and add:

```php
    public function test_bracket_auto_advances_winners_and_third_place(): void
    {
        $overlay = Overlay::create([
            'name' => 'B', 'type' => 'group_standings',
            'windows' => [[
                'id' => 'w1', 'type' => 'bracket', 'name' => 'T',
                'bracket_data' => ['size' => 8, 'matches' => [
                    ['round' => 'Pusfinaliai', 'team1' => 'A', 'team2' => 'B', 'sets1' => '6 6', 'sets2' => '2 3', 'winner' => 1],
                    ['round' => 'Pusfinaliai', 'team1' => 'C', 'team2' => 'D', 'sets1' => '', 'sets2' => '', 'winner' => 2],
                    ['round' => 'Finalas', 'team1' => '', 'team2' => '', 'sets1' => '', 'sets2' => '', 'winner' => null],
                    ['round' => 'Dėl 3 vietos', 'team1' => '', 'team2' => '', 'sets1' => '', 'sets2' => '', 'winner' => null],
                ]],
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'bracket'])
            ->assertJsonPath('bracket.rounds.0.title', 'Pusfinaliai')
            ->assertJsonPath('bracket.rounds.0.matches.0.sets1', '6 6')
            ->assertJsonPath('bracket.rounds.1.title', 'Finalas')
            ->assertJsonPath('bracket.rounds.1.matches.0.team1', 'A')
            ->assertJsonPath('bracket.rounds.1.matches.0.team2', 'D')
            ->assertJsonPath('bracket.third.team1', 'B')
            ->assertJsonPath('bracket.third.team2', 'C');
    }
```

- [ ] **Step 3: Run** — `php artisan test --filter=OverlayEndpointTest` (all PASS).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/OverlayController.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: bracket auto-advances winners and derives 3rd place"
```

---

## Task 3: overlay renderer — set columns per team

**Files:**
- Modify: `resources/views/overlays/window.blade.php`

Render check only.

- [ ] **Step 1: Update the bracket branch** `team` / `matchBox` helpers (they currently use a
  single `.sc` score span). Replace them with set-cell rendering:

```js
        const setCells = (sets) => (sets || '').trim().split(/\s+/).filter(Boolean)
            .map((g) => `<span class="g">${g}</span>`).join('');
        const team = (name, sets, win) =>
            `<div class="team ${win ? 'win' : ''}"><span class="nm">${name || 'TBD'}</span><span class="sets">${setCells(sets)}</span></div>`;
        const matchBox = (m) =>
            `<div class="match">${team(m.team1, m.sets1, m.winner === 1)}${team(m.team2, m.sets2, m.winner === 2)}</div>`;
```

(The rest of the bracket branch — rounds loop, `match-slot`, `is-last`, 3rd-place block — stays.)

- [ ] **Step 2: Update the bracket team styles** in `@section('styles')`. Replace the existing
  `.team .sc { ... }` and `.team.win .sc { ... }` rules with set-cell rules:

```css
    .team .sets { display: flex; gap: 6px; }
    .team .g { font-family: 'Oswald', sans-serif; font-variant-numeric: tabular-nums;
        min-width: 14px; text-align: center; color: var(--ov-muted); }
    .team.win .g { color: var(--ov-accent); }
```

(Keep `.team .nm`, `.team.win`, `.team.win .nm` as they are.)

- [ ] **Step 3: Render check**

```
php artisan view:clear
php artisan tinker --execute="\$o=App\Models\Overlay::firstOrCreate(['name'=>'wv2','type'=>'group_standings']); echo app('Illuminate\Contracts\Http\Kernel')->handle(Illuminate\Http\Request::create('/overlay/'.\$o->token,'GET'))->status();"
```
Expected: `200`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/overlays/window.blade.php
git commit -m "feat: render bracket set games as columns per team"
```

---

## Task 4: builder — set fields + helper

**Files:**
- Modify: `app/Filament/Resources/OverlayResource.php`

`php -l` + suite green.

- [ ] **Step 1: Update the `bracket_data.matches` Repeater schema** — replace the
  `score1`/`score2` TextInputs (if present) with `sets1`/`sets2`, and keep `team1`/`team2`/`winner`.
  The match repeater item schema should be:

```php
                                ->schema([
                                    Hidden::make('round'),
                                    TextInput::make('team1')->label('Pora 1'),
                                    TextInput::make('team2')->label('Pora 2'),
                                    TextInput::make('sets1')->label('Setai (Pora 1), pvz. 6 6'),
                                    TextInput::make('sets2')->label('Setai (Pora 2), pvz. 2 3'),
                                    Select::make('winner')->label('Nugalėtojas')
                                        ->options([1 => '1-as', 2 => '2-as'])->placeholder('—'),
                                ])
```

- [ ] **Step 2: Add a helper note** — on the `Repeater::make('bracket_data.matches')` add
  `->helperText('Pildyk tik 1-ą raundą — vėlesni raundai užsipildo automatiškai pagal nugalėtojus.')`.

- [ ] **Step 3: Verify**

`php -l app/Filament/Resources/OverlayResource.php`; `php artisan test` (green apart from the
pre-existing `ExampleTest`). Manually: bracket window, size 8, fill round-1 pairs + winners +
sets, save; confirm no errors.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/OverlayResource.php
git commit -m "feat: bracket builder set fields + auto-advance helper"
```

---

## Done criteria

- `php artisan test` green (apart from the unrelated stock `ExampleTest`).
- Operator fills round 1 + winners + sets; overlay auto-fills later rounds, 3rd place from SF
  losers, shows set columns, highlights winners.

## Manual OBS verification (Tadas)

1. Deploy (git pull + clear caches; no migration).
2. Bracket window size 8: fill QF pairs, mark winners + sets; Play in OBS.
3. Confirm SF/Final names auto-appear from winners, 3rd place shows the two SF losers, set
   games render as columns, winning teams highlighted.
