# Schedule (Order of Play) Overlays Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a four-variant "Tvarkaraštis" (schedule) overlay window driven by tournament matches.

**Architecture:** The push bridge sends a normalized `matches` array in the snapshot; the server filters/sorts/groups it per the window's variant; the renderer draws four layouts. Mirrors the existing groups/bracket/sponsors overlay model.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11 (SQLite :memory:), Blade + vanilla JS, Node push script.

**Spec:** `docs/superpowers/specs/2026-06-17-schedule-overlays-design.md`

> Deploy reminder: NEVER run artisan config:cache/route:cache/view:cache. No migrations. Restart `node push.js` after deploy (snapshot gains `matches`).

## File Structure

- `tools/overlay-push/push.js` — `fetchMatches` + `normalizeMatch`, add `matches` to snapshot.
- `app/Http/Controllers/OverlayController.php` — ingest `matches`; `data()` schedule branch.
- `app/Services/OverlayData.php` — `resolveSchedule`, `courts`.
- `app/Filament/Resources/OverlayResource.php` — schedule window fields.
- `resources/views/overlays/window.blade.php` — `schedule` render branch + CSS.
- `tests/Feature/OverlayEndpointTest.php` — schedule resolver + ingest tests.

---

## Task 1: push — fetch and normalize matches

**Files:** `tools/overlay-push/push.js`

- [ ] **Step 1: Add `normalizeMatch` + `fetchMatches`** near the other fetchers (after `fetchDraws`):

```js
// ── Susitikimai (order of play) ─────────────────────────────
function normalizeMatch(m) {
  const names = (p) => (p && p.users)
    ? p.users.map((u) => `${u.name || ''} ${u.surname || ''}`.trim()).filter(Boolean) : [];
  const e1 = m.entry1 && m.entry1.id;
  const e2 = m.entry2 && m.entry2.id;
  const w = m.winner && m.winner.id;
  let winner = null;
  if (w != null && e1 != null && w === e1) winner = 1;
  else if (w != null && e2 != null && w === e2) winner = 2;
  return {
    id: m.id,
    date: (m.date || '').slice(0, 10),
    time: m.time || null,
    duration: m.duration || null,
    court: (m.court && m.court.name) || null,
    court_id: (m.court && m.court.id) || null,
    category_id: (m.tournamentCategory && m.tournamentCategory.id) || null,
    category: (m.tournamentCategory && m.tournamentCategory.category && m.tournamentCategory.category.name) || null,
    status: m.status || null,
    in_progress: !!m.isMatchInProgress,
    round: m.round || null,
    segment: m.bracketType || null,
    score: m.score || null,
    team1: names(m.participant1),
    team2: names(m.participant2),
    winner,
  };
}

async function fetchMatches(tournamentId) {
  const data = await gql(`{
    matches(filter: { tournament: ${tournamentId} }) {
      id time date duration status isMatchInProgress round bracketType score
      court { id name }
      tournamentCategory { id category { name } }
      entry1 { id } entry2 { id } winner { id }
      participant1 { users { name surname } }
      participant2 { users { name surname } }
    }
  }`);
  return (data.matches || []).map(normalizeMatch);
}
```

- [ ] **Step 2: Fetch matches in `pushOnce`** — before building the `snapshot` object add:

```js
  let matches = [];
  try { matches = await fetchMatches(tournamentId); } catch (e) { console.error(`  ! Matches: ${e.message}`); }
```

- [ ] **Step 3: Add `matches` to the snapshot object** (alongside `brackets_by_category`):

```js
    brackets_by_category: bracketsByCategory,
    matches,
```

- [ ] **Step 4: Syntax check** — `node --check tools/overlay-push/push.js` (prints nothing).

- [ ] **Step 5: Commit**

```bash
git add tools/overlay-push/push.js
git commit -m "feat: push sends normalized matches for schedule overlays"
```

---

## Task 2: server — ingest, resolver, courts, data branch (TDD)

**Files:** `app/Http/Controllers/OverlayController.php`, `app/Services/OverlayData.php`, `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Write failing tests** — add to `OverlayEndpointTest.php` before the final `}`. Helper + four tests:

```php
    private function scheduleOverlay(string $variant, array $extra = []): Overlay
    {
        $mk = fn ($id, $date, $time, $court, $courtId, $cat, $status, $inProg) => [
            'id' => $id, 'date' => $date, 'time' => $time, 'duration' => 60,
            'court' => $court, 'court_id' => $courtId, 'category_id' => $cat, 'category' => 'Vyrai 40+',
            'status' => $status, 'in_progress' => $inProg, 'round' => 'R1', 'segment' => 'main',
            'score' => '6:2', 'team1' => ['A B'], 'team2' => ['C D'], 'winner' => 1,
        ];

        OverlaySnapshot::updateOrCreate(['tournament_external_id' => '10424'], ['payload' => [
            'matches' => [
                $mk(1, '2026-04-18', '11:00', 'Court 7', 7, 53642, 'completed', false),
                $mk(2, '2026-04-18', '12:00', 'Court 7', 7, 53642, 'pending', false),
                $mk(3, '2026-04-18', '11:00', 'Court 8', 8, 53636, 'pending', true),
                $mk(4, '2026-04-19', '11:00', 'Court 8', 8, 53642, 'pending', false),
            ],
        ]]);

        return Overlay::create([
            'name' => 'S', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [array_merge([
                'id' => 'w1', 'type' => 'schedule', 'name' => 'T', 'schedule_variant' => $variant,
                'date' => '2026-04-18', 'category_ids' => [], 'courts' => [], 'limit' => 6,
            ], $extra)],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);
    }

    public function test_schedule_by_court_groups_and_sorts(): void
    {
        $o = $this->scheduleOverlay('by_court');
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'schedule', 'schedule_variant' => 'by_court']);

        // Two courts on 2026-04-18; Court 7 has 2 matches sorted by time.
        $this->assertSame(['Court 7', 'Court 8'], collect($res->json('schedule.groups'))->pluck('heading')->all());
        $this->assertSame(['11:00', '12:00'], collect($res->json('schedule.groups.0.matches'))->pluck('time')->all());
    }

    public function test_schedule_now_keeps_only_in_progress(): void
    {
        $o = $this->scheduleOverlay('now');
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk();
        $this->assertSame([3], collect($res->json('schedule.items'))->pluck('id')->all());
    }

    public function test_schedule_next_keeps_pending_sorted_by_time(): void
    {
        $o = $this->scheduleOverlay('next');
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk();
        // date filter keeps the 18th; pending ids 2 (12:00) and 3 (11:00) → sorted 3,2.
        $this->assertSame([3, 2], collect($res->json('schedule.items'))->pluck('id')->all());
    }

    public function test_schedule_filters_by_category_and_court(): void
    {
        $o = $this->scheduleOverlay('by_court', ['category_ids' => [53642], 'courts' => [7]]);
        $res = $this->getJson("/overlay/{$o->token}/data")->assertOk();
        $this->assertSame(['Court 7'], collect($res->json('schedule.groups'))->pluck('heading')->all());
        $this->assertSame([1, 2], collect($res->json('schedule.groups.0.matches'))->pluck('id')->all());
    }
```

- [ ] **Step 2: Run tests, verify they fail** — `php artisan test --filter=OverlayEndpointTest` (new tests error: no schedule branch).

- [ ] **Step 3: Add `resolveSchedule` + `courts` to `OverlayData.php`** (after `resolveWindow`):

```php
    /**
     * Resolve a schedule (order-of-play) window from the snapshot matches.
     *
     * @param  array<string,mixed>  $window
     * @return array<string,mixed>
     */
    public function resolveSchedule(string $tournamentId, array $window): array
    {
        $variant = $window['schedule_variant'] ?? 'by_court';
        $matches = $this->payload($tournamentId)['matches'] ?? [];

        if (! empty($window['date'])) {
            $date = substr((string) $window['date'], 0, 10);
            $matches = array_filter($matches, fn ($m) => ($m['date'] ?? null) === $date);
        }
        if (! empty($window['category_ids'])) {
            $cats = array_map('intval', $window['category_ids']);
            $matches = array_filter($matches, fn ($m) => in_array((int) ($m['category_id'] ?? 0), $cats, true));
        }
        if (! empty($window['courts'])) {
            $courts = array_map('intval', $window['courts']);
            $matches = array_filter($matches, fn ($m) => in_array((int) ($m['court_id'] ?? 0), $courts, true));
        }
        $matches = array_values($matches);

        $byTime = fn ($a, $b) => strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? ''));

        if ($variant === 'now' || $variant === 'next') {
            $items = $variant === 'now'
                ? array_filter($matches, fn ($m) => ! empty($m['in_progress']))
                : array_filter($matches, fn ($m) => ($m['status'] ?? null) === 'pending');
            $items = array_values($items);
            usort($items, $byTime);
            $limit = (int) ($window['limit'] ?? 0);
            if ($limit > 0) {
                $items = array_slice($items, 0, $limit);
            }

            return ['variant' => $variant, 'items' => $items];
        }

        usort($matches, $byTime);
        $key = $variant === 'by_time' ? 'time' : 'court';
        $groups = [];
        foreach ($matches as $m) {
            $groups[(string) ($m[$key] ?? '—')][] = $m;
        }
        if ($variant === 'by_court') {
            ksort($groups);
        }

        return ['variant' => $variant, 'groups' => array_map(
            fn ($heading, $ms) => ['heading' => $heading, 'matches' => $ms],
            array_keys($groups),
            array_values($groups),
        )];
    }

    /**
     * Distinct courts (id => name) from the snapshot matches, for the admin select.
     *
     * @return array<int,string>
     */
    public function courts(string $tournamentId): array
    {
        $out = [];
        foreach ($this->payload($tournamentId)['matches'] ?? [] as $m) {
            $id = $m['court_id'] ?? null;
            if ($id) {
                $out[(int) $id] = $m['court'] ?? ('#' . $id);
            }
        }
        asort($out);

        return $out;
    }
```

- [ ] **Step 4: Add the `schedule` branch to `OverlayController::data`** — before `if ($type === 'bracket')`:

```php
        if ($type === 'schedule') {
            $payload['schedule_variant'] = $window['schedule_variant'] ?? 'by_court';
            $payload['schedule'] = $data->resolveSchedule((string) $overlay->tournament_external_id, $window);
        } elseif ($type === 'bracket') {
```

(Change the existing `if ($type === 'bracket')` to `} elseif ($type === 'bracket')` and keep the rest of the chain; the final `else` for groups stays.)

- [ ] **Step 5: Accept `matches` in `OverlayController::ingest`** — add `'matches' => 'array'` to the validation rules and `'matches' => $validated['matches'] ?? []` to the stored payload array.

- [ ] **Step 6: Run tests** — `php artisan test --filter=OverlayEndpointTest` (all PASS).

- [ ] **Step 7: Commit**

```bash
git add app/Services/OverlayData.php app/Http/Controllers/OverlayController.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: schedule resolver + matches ingest"
```

---

## Task 3: admin — schedule window fields

**Files:** `app/Filament/Resources/OverlayResource.php`

- [ ] **Step 1: Add the type option** — in the window `type` Select options array add `'schedule' => 'Tvarkaraštis'`.

- [ ] **Step 2: Add `Filament\Forms\Components\DatePicker` import** at the top (with the other component `use` lines).

- [ ] **Step 3: Add schedule fields** — insert after the bracket `segments` Select, before the sponsors `variant` Select:

```php
                            Select::make('schedule_variant')
                                ->label('Variantas')
                                ->options([
                                    'by_court' => 'Pagal kortą',
                                    'by_time'  => 'Pagal laiką',
                                    'now'      => 'Dabar žaidžiama',
                                    'next'     => 'Toliau',
                                ])
                                ->default('by_court')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            DatePicker::make('date')
                                ->label('Data')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            Select::make('category_ids')
                                ->label('Kategorijos')
                                ->placeholder('Visos kategorijos')
                                ->multiple()
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
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            Select::make('courts')
                                ->label('Kortai')
                                ->placeholder('Visi kortai')
                                ->multiple()
                                ->options(function ($livewire) {
                                    $tid = data_get($livewire, 'data.tournament_external_id');
                                    return $tid ? app(OverlayData::class)->courts((string) $tid) : [];
                                })
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            TextInput::make('limit')
                                ->label('Kiek rodyti (Dabar / Toliau)')
                                ->numeric()->minValue(1)->default(6)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),
```

- [ ] **Step 4: Render check** — `php artisan view:clear` then load the admin create page is manual; instead verify the resource compiles:

```
php artisan about >/dev/null && echo OK
```

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/OverlayResource.php
git commit -m "feat: schedule window admin fields"
```

---

## Task 4: renderer — schedule branch + styles

**Files:** `resources/views/overlays/window.blade.php`

- [ ] **Step 1: Add the `schedule` render branch** in `@section('render_fn_body')`, right after the bracket branch's closing `return;` (before the sponsors/groups code). Use `headerHtml` already defined above:

```js
    // ── Schedule (order of play) ────────────────────────────────
    if ((d.window_type || 'groups') === 'schedule') {
        const sc = d.schedule || {};
        const variant = d.schedule_variant || 'by_court';
        const pair = (t) => (t && t.length) ? t.join(' / ') : 'TBD';
        const teams = (m) =>
            `<div class="sc-teams"><span class="${m.winner === 1 ? 'win' : ''}">${pair(m.team1)}</span>`
          + `<span class="${m.winner === 2 ? 'win' : ''}">${pair(m.team2)}</span></div>`;
        const meta = (parts) => `<div class="sc-meta">${parts.filter(Boolean).join(' · ')}</div>`;

        let html = headerHtml + '<div class="sc-wrap">';

        if (variant === 'now' || variant === 'next') {
            const items = sc.items || [];
            if (!items.length) {
                html += '<div class="sc-empty">Nėra suplanuotų rungtynių</div>';
            } else {
                html += `<div class="sc-list ${variant}">`;
                for (const m of items) {
                    html += `<div class="sc-card${variant === 'now' ? ' live' : ''}">`
                        + meta([m.time, m.court, m.category].filter(Boolean))
                        + teams(m)
                        + (m.score ? `<div class="sc-score">${m.score}</div>` : '')
                        + '</div>';
                }
                html += '</div>';
            }
        } else {
            const groups = sc.groups || [];
            if (!groups.length) {
                html += '<div class="sc-empty">Nėra suplanuotų rungtynių</div>';
            } else {
                html += `<div class="sc-cols ${variant}">`;
                for (const g of groups) {
                    html += `<div class="sc-col"><div class="sc-col-head">${g.heading || '—'}</div>`;
                    for (const m of g.matches) {
                        const tag = variant === 'by_time' ? m.court : m.time;
                        html += `<div class="sc-row">${meta([tag, m.category].filter(Boolean))}${teams(m)}`
                            + (m.score ? `<div class="sc-score">${m.score}</div>` : '') + '</div>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            }
        }

        html += '</div>';
        stage.innerHTML = html;
        return;
    }
```

- [ ] **Step 2: Add styles** in `@section('styles')` after the bracket/segment styles:

```css
    /* ── Schedule (order of play) ─────────────────────────────── */
    .sc-wrap { display: flex; flex-direction: column; gap: 14px; }
    .sc-empty { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: .1em;
        color: var(--ov-muted); padding: 18px; }
    .sc-cols { display: flex; flex-wrap: wrap; gap: 18px; align-items: flex-start; }
    .sc-col { flex: 1 1 200px; min-width: 200px; background: var(--ov-bg);
        border: 1px solid rgba(127,127,127,.22); border-radius: 8px; overflow: hidden; }
    .sc-col-head { font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .08em; font-size: 14px; color: var(--ov-accent);
        padding: 9px 13px; border-bottom: 1px solid rgba(127,127,127,.22); }
    .sc-row, .sc-card { padding: 9px 13px; border-bottom: 1px solid rgba(127,127,127,.12); }
    .sc-list { display: flex; flex-direction: column; gap: 10px; }
    .sc-card { border: 1px solid rgba(127,127,127,.22); border-left: 3px solid rgba(127,127,127,.4);
        border-radius: 6px; background: var(--ov-bg); }
    .sc-card.live { border-left-color: var(--ov-accent); box-shadow: 0 0 16px -6px var(--ov-accent); }
    .sc-meta { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: .06em;
        font-size: 11px; color: var(--ov-muted); margin-bottom: 4px; }
    .sc-teams { display: flex; flex-direction: column; gap: 2px; font-family: 'Barlow', sans-serif;
        font-size: 14px; color: var(--ov-text); }
    .sc-teams .win { color: var(--ov-accent); font-weight: 700; }
    .sc-score { font-family: 'Oswald', sans-serif; font-variant-numeric: tabular-nums;
        font-size: 12px; color: var(--ov-muted); margin-top: 4px; }
```

- [ ] **Step 3: Render check**

```
php artisan view:clear
php artisan tinker --execute="\$o=App\Models\Overlay::firstOrCreate(['name'=>'wv2','type'=>'group_standings']); echo app('Illuminate\Contracts\Http\Kernel')->handle(Illuminate\Http\Request::create('/overlay/'.\$o->token,'GET'))->status();"
```
Expected: `200`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/overlays/window.blade.php
git commit -m "feat: schedule overlay rendering (4 variants)"
```

---

## Done criteria

- `php artisan test --filter=OverlayEndpointTest` green.
- Snapshot carries `matches`; schedule window resolves all four variants with date/category/court/limit filters.
- The overlay renders by-court / by-time grids and now / next lists, theme-driven, with an empty state.

## Manual OBS verification (Tadas)

1. Deploy (git pull + clear caches; no migration). Restart `node push.js`.
2. Create a "Tvarkaraštis" window, pick a variant + date; confirm matches show correctly per court / time, and that "Dabar žaidžiama" / "Toliau" populate when the organizer marks matches in progress / pending.
