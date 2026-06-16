# Bracket Builder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace raw-JSON bracket entry with an admin builder — pick 8/16, auto-generate the round skeleton (incl. 3rd-place), fill values — and render the bracket as themed round columns + a separate 3rd-place block.

**Architecture:** `bracket_data` is a flat `{size, matches:[{round,team1,score1,team2,score2,winner}]}`. A pure `Overlay::bracketSkeleton()` generates the skeleton; the Filament window editor generates it on size change and edits matches in a fixed (non-addable) repeater. The data endpoint groups the flat matches into ordered rounds + a `third` match; the overlay renders columns + a 3rd-place block, themed by `--ov-*`.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11, Blade + vanilla JS.

**Spec:** `docs/superpowers/specs/2026-06-16-bracket-builder-design.md`

> Deploy reminder: NEVER run artisan config:cache/route:cache/view:cache. No migrations.

## File Structure

- `app/Models/Overlay.php` — add `bracketSkeleton(int $size): array`
- `app/Http/Controllers/OverlayController.php` — bracket payload groups flat matches
- `resources/views/overlays/window.blade.php` — bracket render (columns + 3rd place) + styles
- `app/Filament/Resources/OverlayResource.php` — bracket builder (size + generator + matches repeater)
- tests

---

## Task 1: Overlay::bracketSkeleton

**Files:**
- Modify: `app/Models/Overlay.php`
- Test: `tests/Unit/BracketSkeletonTest.php`

- [ ] **Step 1: Failing test** `tests/Unit/BracketSkeletonTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Overlay;
use PHPUnit\Framework\TestCase;

class BracketSkeletonTest extends TestCase
{
    public function test_skeleton_8(): void
    {
        $m = Overlay::bracketSkeleton(8);
        $this->assertCount(8, $m);
        $this->assertSame('Ketvirtfinaliai', $m[0]['round']);
        $this->assertSame('Finalas', $m[6]['round']);
        $this->assertSame('Dėl 3 vietos', $m[7]['round']);
        $this->assertNull($m[0]['winner']);
        $this->assertSame('', $m[0]['team1']);
    }

    public function test_skeleton_16(): void
    {
        $m = Overlay::bracketSkeleton(16);
        $this->assertCount(16, $m);
        $this->assertSame('1/8 finalio', $m[0]['round']);
        $this->assertSame('Ketvirtfinaliai', $m[8]['round']);
        $this->assertSame('Finalas', $m[14]['round']);
        $this->assertSame('Dėl 3 vietos', $m[15]['round']);
    }
}
```

- [ ] **Step 2: Run, verify FAIL** — `php artisan test --filter=BracketSkeletonTest`

- [ ] **Step 3: Add the method** to `app/Models/Overlay.php`:

```php
    /**
     * Generate an empty single-elimination bracket (flat match list) for the
     * given draw size, including the 3rd-place match.
     *
     * @return list<array<string,mixed>>
     */
    public static function bracketSkeleton(int $size): array
    {
        $rounds = $size === 16
            ? [['1/8 finalio', 8], ['Ketvirtfinaliai', 4], ['Pusfinaliai', 2], ['Finalas', 1]]
            : [['Ketvirtfinaliai', 4], ['Pusfinaliai', 2], ['Finalas', 1]];

        $blank = fn (string $round) => [
            'round' => $round, 'team1' => '', 'score1' => '', 'team2' => '', 'score2' => '', 'winner' => null,
        ];

        $matches = [];
        foreach ($rounds as [$title, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $matches[] = $blank($title);
            }
        }
        $matches[] = $blank('Dėl 3 vietos');

        return $matches;
    }
```

- [ ] **Step 4: Run, verify PASS** — `php artisan test --filter=BracketSkeletonTest`

- [ ] **Step 5: Commit**

```bash
git add app/Models/Overlay.php tests/Unit/BracketSkeletonTest.php
git commit -m "feat: add bracket skeleton generator (8/16 + 3rd place)"
```

---

## Task 2: data endpoint — group flat matches into rounds + third

**Files:**
- Modify: `app/Http/Controllers/OverlayController.php`
- Test: `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Update the bracket branch** in `data()`. Replace the current bracket case:

```php
        if ($type === 'bracket') {
            $payload['bracket'] = $this->buildBracket($window['bracket_data'] ?? []);
        } elseif ($type === 'sponsors') {
```

(Keep the `sponsors` and `else`/groups branches as they are.)

- [ ] **Step 2: Add the private helper** to `OverlayController`:

```php
    /**
     * Group a flat bracket match list into ordered rounds, extracting the
     * 3rd-place match separately.
     *
     * @param  array<string,mixed>  $bracketData
     * @return array{rounds:list<array{title:string,matches:list<array<string,mixed>>}>,third:?array<string,mixed>}
     */
    private function buildBracket(array $bracketData): array
    {
        $rounds = [];
        $index = [];
        $third = null;

        foreach ($bracketData['matches'] ?? [] as $m) {
            $entry = [
                'team1'  => $m['team1'] ?? '',
                'score1' => $m['score1'] ?? '',
                'team2'  => $m['team2'] ?? '',
                'score2' => $m['score2'] ?? '',
                'winner' => $m['winner'] ?? null,
            ];

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

        return ['rounds' => $rounds, 'third' => $third];
    }
```

- [ ] **Step 3: Add a feature test** to `tests/Feature/OverlayEndpointTest.php`:

```php
    public function test_data_returns_bracket_grouped_with_third_place(): void
    {
        $overlay = Overlay::create([
            'name' => 'B', 'type' => 'group_standings',
            'windows' => [[
                'id' => 'w1', 'type' => 'bracket', 'name' => 'Tinklelis',
                'bracket_data' => ['size' => 8, 'matches' => [
                    ['round' => 'Pusfinaliai', 'team1' => 'A', 'score1' => '6:2', 'team2' => 'B', 'score2' => '', 'winner' => 1],
                    ['round' => 'Pusfinaliai', 'team1' => 'C', 'score1' => '', 'team2' => 'D', 'score2' => '', 'winner' => 2],
                    ['round' => 'Finalas', 'team1' => 'A', 'score1' => '', 'team2' => 'D', 'score2' => '', 'winner' => null],
                    ['round' => 'Dėl 3 vietos', 'team1' => 'B', 'score1' => '', 'team2' => 'C', 'score2' => '', 'winner' => 2],
                ]],
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'bracket'])
            ->assertJsonPath('bracket.rounds.0.title', 'Pusfinaliai')
            ->assertJsonCount(2, 'bracket.rounds.0.matches')
            ->assertJsonPath('bracket.rounds.1.title', 'Finalas')
            ->assertJsonPath('bracket.third.team1', 'B')
            ->assertJsonPath('bracket.third.winner', 2);
    }
```

- [ ] **Step 4: Run** — `php artisan test --filter=OverlayEndpointTest` (all PASS).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OverlayController.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: bracket data payload groups matches into rounds + third place"
```

---

## Task 3: overlay renderer — bracket columns + 3rd-place block

**Files:**
- Modify: `resources/views/overlays/window.blade.php`

Render check only.

- [ ] **Step 1: Replace the bracket branch** in `@section('render_fn_body')`. The current
  bracket branch reads `d.rounds` and `m.teams[]`. Replace it (it sits after the `headerHtml`
  definition) with this, which reads the new `d.bracket` shape:

```js
    if ((d.window_type || 'groups') === 'bracket') {
        const b = d.bracket || { rounds: [], third: null };
        const team = (name, score, win) =>
            `<div class="team ${win ? 'win' : ''}"><span>${name || 'TBD'}</span><span>${score ?? ''}</span></div>`;
        const matchBox = (m) =>
            `<div class="match">${team(m.team1, m.score1, m.winner === 1)}${team(m.team2, m.score2, m.winner === 2)}</div>`;

        let html = headerHtml + `<div class="bracket">`;
        for (const round of b.rounds) {
            html += `<div class="round"><div class="round-title">${round.title}</div>`;
            for (const m of round.matches) html += matchBox(m);
            html += `</div>`;
        }
        if (b.third) {
            html += `<div class="round third"><div class="round-title">Dėl 3 vietos</div>${matchBox(b.third)}</div>`;
        }
        html += `</div>`;
        stage.innerHTML = html;
        return;
    }
```

- [ ] **Step 2: Add bracket styles** to `@section('styles')` (the `.bracket/.round/.match/.team`
  rules exist; add round titles + align rounds to top + 3rd-place accent). Append:

```css
    .bracket { align-items: flex-start; }
    .round-title { font-family: 'Oswald',sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .08em; font-size: 12px; color: var(--ov-muted); margin-bottom: 6px; text-align: center; }
    .round.third { margin-left: 8px; }
    .round.third .round-title { color: var(--ov-accent); }
    .round.third .match { border-color: var(--ov-accent); }
```

(The existing `.round { display:flex; flex-direction:column; gap:22px; }` will stack the title
above its matches — that's intended; the title sits at the top of each column.)

- [ ] **Step 3: Render check**

```
php artisan view:clear
php artisan tinker --execute="\$o=App\Models\Overlay::firstOrCreate(['name'=>'wv2','type'=>'group_standings']); echo app('Illuminate\Contracts\Http\Kernel')->handle(Illuminate\Http\Request::create('/overlay/'.\$o->token,'GET'))->status();"
```
Expected: `200`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/overlays/window.blade.php
git commit -m "feat: render bracket as themed round columns with 3rd-place block"
```

---

## Task 4: Filament bracket builder (size + generator + matches repeater)

**Files:**
- Modify: `app/Filament/Resources/OverlayResource.php`

UI task — `php -l` + suite green.

- [ ] **Step 1: Replace the old `bracket_data` Textarea** (the one visible when
  `type === 'bracket'`) with a size select + a fixed matches repeater. Add these two fields
  in the windows repeater item schema (both visible only for bracket):

```php
                            Select::make('bracket_data.size')
                                ->label('Tinklelio dydis')
                                ->options([8 => '8 komandų', 16 => '16 komandų'])
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $set('bracket_data.matches', \App\Models\Overlay::bracketSkeleton((int) $state));
                                    }
                                })
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'bracket'),

                            Repeater::make('bracket_data.matches')
                                ->label('Mačai')
                                ->addable(false)->deletable(false)->reorderable(false)
                                ->itemLabel(fn (array $state) => $state['round'] ?? 'Mačas')
                                ->schema([
                                    Hidden::make('round'),
                                    TextInput::make('team1')->label('Pora 1'),
                                    TextInput::make('score1')->label('Rez. 1'),
                                    TextInput::make('team2')->label('Pora 2'),
                                    TextInput::make('score2')->label('Rez. 2'),
                                    Select::make('winner')->label('Nugalėtojas')
                                        ->options([1 => '1-as', 2 => '2-as'])->placeholder('—'),
                                ])
                                ->columns(2)
                                ->collapsed()
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'bracket'),
```

Remove the previous `Textarea::make('bracket_data')` field entirely.

- [ ] **Step 2: Verify**

`php -l app/Filament/Resources/OverlayResource.php`; `php artisan test` (green apart from the
pre-existing `ExampleTest`). Manually: open `/admin/overlays`, add a bracket window, pick size
8 → confirm 8 match rows appear labeled by round (incl. "Dėl 3 vietos"); fill a couple, save.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/OverlayResource.php
git commit -m "feat: admin bracket builder (size generator + matches editor)"
```

---

## Done criteria

- `php artisan test` green (apart from the unrelated stock `ExampleTest`).
- Admin: pick 8/16 → skeleton generates (incl. 3rd place); fill names/scores/winner; save.
- Overlay renders bracket as themed round columns + a distinct 3rd-place block, with animation.

## Manual OBS verification (Tadas)

1. Deploy (git pull + clear caches; no migration). 
2. Create a bracket window, size 8, fill some results, mark winners; Play it in OBS.
3. Confirm columns (QF/SF/Final) + the "Dėl 3 vietos" block render and theme correctly.
4. Repeat with size 16.
