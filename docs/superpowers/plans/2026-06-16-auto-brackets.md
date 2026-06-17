# Automatic Brackets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Populate brackets automatically from the Tournated `draws` data (pushed in the snapshot), render them per category, and remove the manual bracket builder.

**Architecture:** The push script parses each category's `draws` into a normalized
`brackets_by_category` (main-draw rounds + 3rd place, with pairs/sets/winner). A bracket
window points at a `category_id`; `OverlayController::data` reads the stored bracket via
`OverlayData::bracketForCategory`. The existing tree renderer is unchanged. Manual bracket
code is deleted.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11, Blade + vanilla JS, Node push script.

**Spec:** `docs/superpowers/specs/2026-06-16-auto-brackets-design.md`

> Deploy reminder: NEVER run artisan config:cache/route:cache/view:cache. No migrations.

## File Structure

- `app/Services/OverlayData.php` — add `bracketForCategory()`
- `app/Http/Controllers/OverlayController.php` — bracket branch reads snapshot; ingest accepts `brackets_by_category`; remove `buildBracket`
- `app/Models/Overlay.php` — remove `bracketSkeleton`, `advanceBracket`
- `app/Filament/Resources/OverlayResource.php` — bracket window = category select; remove `advanceBracketWindows`
- `app/Filament/Resources/OverlayResource/Pages/{Edit,Create}Overlay.php` — remove mutate/afterSave hooks
- `tools/overlay-push/push.js` — parse draws → `brackets_by_category`
- tests — add snapshot-bracket feature test; delete `BracketSkeletonTest`, `AdvanceBracketTest`; remove old manual bracket endpoint test

---

## Task 1: backend read path (bracketForCategory + endpoint + ingest)

**Files:**
- Modify: `app/Services/OverlayData.php`, `app/Http/Controllers/OverlayController.php`
- Test: `tests/Feature/OverlayEndpointTest.php`, `tests/Feature/OverlayIngestTest.php`

- [ ] **Step 1: Add `bracketForCategory` to `OverlayData`:**

```php
    /**
     * The stored normalized bracket for a category, from the pushed snapshot.
     *
     * @return array{rounds:array<int,mixed>,third:?array<string,mixed>}
     */
    public function bracketForCategory(string $tournamentId, int $categoryId): array
    {
        $byCat = $this->payload($tournamentId)['brackets_by_category'] ?? [];
        $b = $byCat[(string) $categoryId] ?? null;

        return [
            'rounds' => $b['rounds'] ?? [],
            'third'  => $b['third'] ?? null,
        ];
    }
```

- [ ] **Step 2: In `OverlayController::data`, replace the bracket branch** so it reads the
  snapshot, and **delete the private `buildBracket` method**:

```php
        if ($type === 'bracket') {
            $payload['bracket'] = $data->bracketForCategory(
                (string) $overlay->tournament_external_id,
                (int) ($window['category_id'] ?? 0),
            );
        } elseif ($type === 'sponsors') {
```

(Keep the sponsors and groups branches unchanged. Remove the whole `private function buildBracket(...) { ... }`.)

- [ ] **Step 3: In `OverlayController::ingest`,** add `brackets_by_category` to the validate
  array (`'brackets_by_category' => 'array'`) and to the stored payload
  (`'brackets_by_category' => $validated['brackets_by_category'] ?? []`).

- [ ] **Step 4: Replace the bracket endpoint test** in `tests/Feature/OverlayEndpointTest.php` —
  delete `test_bracket_auto_advances_winners_and_third_place` and add:

```php
    public function test_bracket_window_returns_category_draw_from_snapshot(): void
    {
        \App\Models\OverlaySnapshot::create([
            'tournament_external_id' => '10424',
            'payload' => [
                'brackets_by_category' => [
                    '53642' => [
                        'rounds' => [
                            ['title' => 'Pusfinaliai', 'matches' => [
                                ['team1' => 'A', 'team2' => 'B', 'sets1' => '6', 'sets2' => '2', 'winner' => 1],
                            ]],
                            ['title' => 'Finalas', 'matches' => [
                                ['team1' => 'A', 'team2' => 'C', 'sets1' => '', 'sets2' => '', 'winner' => null],
                            ]],
                        ],
                        'third' => ['team1' => 'B', 'team2' => 'D', 'sets1' => '', 'sets2' => '', 'winner' => 2],
                    ],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'B', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [['id' => 'w1', 'type' => 'bracket', 'name' => 'T', 'category_id' => 53642]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'bracket'])
            ->assertJsonPath('bracket.rounds.0.title', 'Pusfinaliai')
            ->assertJsonPath('bracket.rounds.1.title', 'Finalas')
            ->assertJsonPath('bracket.third.team1', 'B');
    }
```

- [ ] **Step 5: Extend the ingest store test** in `tests/Feature/OverlayIngestTest.php` —
  in `test_ingest_stores_snapshot_with_valid_token` add to the posted payload
  `'brackets_by_category' => ['53642' => ['rounds' => [], 'third' => null]]` and assert
  `$this->assertArrayHasKey('53642', $snapshot->payload['brackets_by_category']);`.

- [ ] **Step 6: Run** — `php artisan test --filter="OverlayEndpointTest|OverlayIngestTest"` (all PASS).

- [ ] **Step 7: Commit**

```bash
git add app/Services/OverlayData.php app/Http/Controllers/OverlayController.php tests/Feature/OverlayEndpointTest.php tests/Feature/OverlayIngestTest.php
git commit -m "feat: bracket window reads category draw from snapshot"
```

---

## Task 2: remove manual bracket code + category-select builder

**Files:**
- Modify: `app/Models/Overlay.php`, `app/Filament/Resources/OverlayResource.php`,
  `app/Filament/Resources/OverlayResource/Pages/EditOverlay.php`,
  `app/Filament/Resources/OverlayResource/Pages/CreateOverlay.php`
- Delete: `tests/Unit/BracketSkeletonTest.php`, `tests/Unit/AdvanceBracketTest.php`

- [ ] **Step 1: Remove dead model methods** — delete `Overlay::bracketSkeleton()` and
  `Overlay::advanceBracket()` from `app/Models/Overlay.php`.

- [ ] **Step 2: Remove `OverlayResource::advanceBracketWindows()`** from `OverlayResource.php`.

- [ ] **Step 3: Remove the save hooks:**
  - `EditOverlay.php`: delete `mutateFormDataBeforeSave()` and `afterSave()`.
  - `CreateOverlay.php`: delete `mutateFormDataBeforeCreate()` (revert to the bare class).

- [ ] **Step 4: Replace the bracket window fields** in `OverlayResource.php`. Remove the
  `bracket_data.size` Select and the `bracket_data.matches` Repeater, and add a single
  category select (visible only for bracket type):

```php
                            Select::make('category_id')
                                ->label('Kategorija (bracketas)')
                                ->options(function ($livewire) {
                                    $tid = data_get($livewire, 'data.tournament_external_id');
                                    if (! $tid) {
                                        return [];
                                    }
                                    $stages = app(OverlayData::class)->categoryStages((string) $tid);
                                    $out = [];
                                    foreach (app(OverlayData::class)->categories((string) $tid) as $c) {
                                        if ($stages[(string) $c['id']]['has_bracket'] ?? false) {
                                            $out[$c['id']] = $c['category']['name'] ?? ('#' . $c['id']);
                                        }
                                    }
                                    return $out;
                                })
                                ->helperText('Bracketas užsipildo automatiškai iš turnyro tinklelio.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'bracket'),
```

(Leave the groups subgroups repeater and sponsors fields untouched. The `Hidden`/`Textarea`
imports may remain unused — harmless.)

- [ ] **Step 5: Delete the obsolete tests:**

```bash
git rm tests/Unit/BracketSkeletonTest.php tests/Unit/AdvanceBracketTest.php
```

- [ ] **Step 6: Verify** — `php -l` on the four modified PHP files; `php artisan test`
  (green apart from the pre-existing `ExampleTest`; the deleted bracket tests should be gone).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Overlay.php app/Filament/Resources/OverlayResource.php app/Filament/Resources/OverlayResource/Pages/EditOverlay.php app/Filament/Resources/OverlayResource/Pages/CreateOverlay.php
git commit -m "refactor: bracket window selects a category; remove manual bracket code"
```

---

## Task 3: push script parses draws into brackets_by_category

**Files:**
- Modify: `tools/overlay-push/push.js`

- [ ] **Step 1: Add an `extractBracket(draw)` helper** (after `fetchDraws`):

```js
function extractBracket(draw) {
  const rounds = draw.rounds || [];
  if (!rounds.length) return null;

  const titleByCount = { 16: '1/16 finalis', 8: '1/8 finalis', 4: 'Ketvirtfinaliai', 2: 'Pusfinaliai', 1: 'Finalas' };
  const pairName = (t) => (t.users || [])
    .map((u) => `${u.user?.name || ''} ${u.user?.surname || ''}`.trim()).filter(Boolean).join(' / ');
  const parseSets = (score) => {
    const s1 = [], s2 = [];
    (score || '').trim().split(/\s+/).filter(Boolean).forEach((tok) => {
      const parts = tok.replace(/[\[\]]/g, '').split(':');
      if (parts.length === 2) { s1.push(parts[0]); s2.push(parts[1]); }
    });
    return [s1.join(' '), s2.join(' ')];
  };
  const matchOf = (seed) => {
    const teams = seed.teams || [];
    const [sets1, sets2] = parseSets(seed.addScore && seed.addScore.addScore);
    let winner = null;
    if (seed.winner && teams[0] && seed.winner.id === teams[0].id) winner = 1;
    else if (seed.winner && teams[1] && seed.winner.id === teams[1].id) winner = 2;
    return {
      team1: teams[0] ? pairName(teams[0]) : '',
      team2: teams[1] ? pairName(teams[1]) : '',
      sets1, sets2, winner,
    };
  };

  // main rounds: match counts halve from the first round down to the final (1)
  const main = [];
  let expected = (rounds[0].seeds || []).length;
  for (const r of rounds) {
    const c = (r.seeds || []).length;
    if (c === expected) { main.push(r); if (c === 1) break; expected = c / 2; }
    else break;
  }
  const outRounds = main.map((r) => ({
    title: titleByCount[(r.seeds || []).length] || r.title || '',
    matches: (r.seeds || []).map(matchOf),
  }));

  // 3rd place: a later 1-match round whose title mentions "3rd"
  let third = null;
  const t = rounds.find((r) => /3rd/i.test(r.title || '') && (r.seeds || []).length === 1);
  if (t) third = matchOf(t.seeds[0]);

  return { rounds: outRounds, third };
}
```

- [ ] **Step 2: Build `brackets_by_category` in `pushOnce`.** Refactor the per-category loop so
  `draws` is fetched once and reused for both `category_stages` and the bracket. After the
  existing `groupsByCategory` loop, replace the separate `categoryStages` draws loop with:

```js
  const categoryStages = {};
  const bracketsByCategory = {};
  for (const cat of categories) {
    const groups = groupsByCategory[String(cat.id)] || [];
    let draws = [];
    try { draws = await fetchDraws(cat.id); } catch (_) { draws = []; }
    categoryStages[String(cat.id)] = {
      has_groups: groups.length > 0,
      has_bracket: draws.length > 0,
      draw_type: draws[0]?.type ?? null,
      draw_size: draws[0]?.size ?? null,
    };
    if (draws[0]) {
      const b = extractBracket(draws[0]);
      if (b && b.rounds.length) bracketsByCategory[String(cat.id)] = b;
    }
  }
```

- [ ] **Step 3: Add `brackets_by_category` to the snapshot object** sent to `/overlay/ingest`
  (alongside `category_stages`): `brackets_by_category: bracketsByCategory,`.

- [ ] **Step 4: Syntax check** — `node --check tools/overlay-push/push.js` (no output).

- [ ] **Step 5: Commit**

```bash
git add tools/overlay-push/push.js
git commit -m "feat: push script extracts brackets from draws"
```

---

## Done criteria

- `php artisan test` green (apart from the unrelated stock `ExampleTest`); manual bracket
  tests removed.
- A bracket window points at a category and renders that category's draw (pairs, set scores,
  winners) from the pushed snapshot; updates as results change.
- No manual bracket builder remains.

## Manual OBS verification (Tadas)

1. Deploy (git pull + clear caches; no migration). Restart `node push.js`.
2. Create a bracket window, select a category that has a draw; Play it in OBS.
3. Confirm the tree shows real pairs, set scores, and winners, and updates as the live draw
   progresses.
