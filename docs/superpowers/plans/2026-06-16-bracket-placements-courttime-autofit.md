# Bracket Placements + Court/Time + Auto-Fit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show court/time on bracket matches and placement sub-brackets (7th/11th/15th place), in a full-screen auto-fitted bracket layout that always fits the canvas.

**Architecture:** The push script extracts court/time per match and groups leftover draw rounds into placement brackets; the snapshot bracket gains `placements` and per-match `court`/`time`. The overlay renders the bracket full-screen (main tree + a row of 3rd-place/placement mini-trees), with court/time captions, and applies a CSS `transform: scale()` measured to fit the viewport.

**Tech Stack:** Laravel 12, PHPUnit 11, Blade + vanilla JS, Node push script.

**Spec:** `docs/superpowers/specs/2026-06-16-bracket-placements-courttime-autofit-design.md`

> Deploy reminder: NEVER run artisan config:cache/route:cache/view:cache. No migrations.

## File Structure

- `tools/overlay-push/push.js` — court/time + placements in `extractBracket`
- `app/Services/OverlayData.php` — `bracketForCategory` passes through `placements`
- `resources/views/overlays/window.blade.php` — full-screen bracket, placements row, court/time, auto-fit
- `tests/Feature/OverlayEndpointTest.php` — placements + court/time passthrough test

---

## Task 1: push script — court/time + placements

**Files:** `tools/overlay-push/push.js`

- [ ] **Step 1: Update `matchOf` inside `extractBracket`** to include court/time. Replace the
  existing `matchOf` with:

```js
  const matchOf = (seed) => {
    const teams = seed.teams || [];
    const [sets1, sets2] = parseSets(seed.addScore && seed.addScore.addScore);
    let winner = null;
    if (seed.winner && teams[0] && seed.winner.id === teams[0].id) winner = 1;
    else if (seed.winner && teams[1] && seed.winner.id === teams[1].id) winner = 2;
    const court = (seed.court && seed.court.name) || (typeof seed.court === 'string' ? seed.court : null);
    return {
      team1: teams[0] ? pairName(teams[0]) : '',
      team2: teams[1] ? pairName(teams[1]) : '',
      sets1, sets2, winner,
      court: court || null,
      time: seed.time || null,
    };
  };
```

- [ ] **Step 2: Add placements extraction** at the end of `extractBracket`, replacing the
  final `let third = ...; ... return { rounds: outRounds, third };` block with:

```js
  const thirdRound = rounds.find((r) => /3rd/i.test(r.title || '') && (r.seeds || []).length === 1);
  const third = thirdRound ? matchOf(thirdRound.seeds[0]) : null;

  // Placement brackets: leftover rounds (not main, not 3rd place) grouped into
  // blocks, each ending in a "Nth place" round.
  const mainSet = new Set(main);
  const placements = [];
  let acc = [];
  for (const r of rounds) {
    if (mainSet.has(r) || r === thirdRound) continue;
    acc.push({ title: '', matches: (r.seeds || []).map(matchOf) });
    const m = (r.title || '').match(/(\d+)\D*place/i);
    if (m) {
      placements.push({ title: `Dėl ${m[1]} vietos`, rounds: acc });
      acc = [];
    }
  }

  return { rounds: outRounds, third, placements };
```

- [ ] **Step 3: Syntax check** — `node --check tools/overlay-push/push.js` (no output).

- [ ] **Step 4: Quick live validation** (optional but recommended) — fetch a real draw and run
  the parse to confirm placements + structure:

```bash
cd "C:\Users\Tadas\Desktop\WEB-zinovai" && curl -s -X POST https://api.tournated.com/graphql -H "Content-Type: application/json" -H "Origin: https://play.padel.lt" -d '{"query":"{ draws(filter: { tournamentCategory: 53642 }) }"}' -o _d.json && node -e "const f=require('./tools/overlay-push/push.js')" 2>/dev/null; rm -f _d.json
```
(If requiring push.js runs the loop, skip this; the `node --check` is the gate.)

- [ ] **Step 5: Commit**

```bash
git add tools/overlay-push/push.js
git commit -m "feat: push extracts court/time and placement brackets"
```

---

## Task 2: server passthrough of placements

**Files:** `app/Services/OverlayData.php`, `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Update `bracketForCategory`** to return placements:

```php
        return [
            'rounds'     => $b['rounds'] ?? [],
            'third'      => $b['third'] ?? null,
            'placements' => $b['placements'] ?? [],
        ];
```

- [ ] **Step 2: Replace the bracket feature test** in `tests/Feature/OverlayEndpointTest.php` —
  update `test_bracket_window_returns_category_draw_from_snapshot` to include placements +
  court/time and assert them. Set the snapshot `brackets_by_category.53642` to:

```php
                    '53642' => [
                        'rounds' => [
                            ['title' => 'Finalas', 'matches' => [
                                ['team1' => 'A', 'team2' => 'C', 'sets1' => '6', 'sets2' => '2', 'winner' => 1, 'court' => 'Kortas 2', 'time' => '10:00'],
                            ]],
                        ],
                        'third' => ['team1' => 'B', 'team2' => 'D', 'sets1' => '', 'sets2' => '', 'winner' => 2],
                        'placements' => [
                            ['title' => 'Dėl 7 vietos', 'rounds' => [
                                ['title' => '', 'matches' => [
                                    ['team1' => 'E', 'team2' => 'F', 'sets1' => '', 'sets2' => '', 'winner' => null],
                                ]],
                            ]],
                        ],
                    ],
```

and assert:

```php
        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'window_type' => 'bracket'])
            ->assertJsonPath('bracket.rounds.0.matches.0.court', 'Kortas 2')
            ->assertJsonPath('bracket.rounds.0.matches.0.time', '10:00')
            ->assertJsonPath('bracket.third.team1', 'B')
            ->assertJsonPath('bracket.placements.0.title', 'Dėl 7 vietos');
```

- [ ] **Step 3: Run** — `php artisan test --filter=OverlayEndpointTest` (all PASS).

- [ ] **Step 4: Commit**

```bash
git add app/Services/OverlayData.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: bracket payload includes placements + court/time"
```

---

## Task 3: renderer — full-screen, placements row, court/time, auto-fit

**Files:** `resources/views/overlays/window.blade.php`

Render check (Blade compiles) + manual OBS verification.

- [ ] **Step 1: Replace the entire bracket branch** in `@section('render_fn_body')` (the
  `if ((d.window_type || 'groups') === 'bracket') { ... }` block) with:

```js
    if ((d.window_type || 'groups') === 'bracket') {
        const b = d.bracket || { rounds: [], third: null, placements: [] };
        const setCells = (sets) => (sets || '').trim().split(/\s+/).filter(Boolean)
            .map((g) => `<span class="g">${g}</span>`).join('');
        const team = (name, sets, win) =>
            `<div class="team ${win ? 'win' : ''}"><span class="nm">${name || 'TBD'}</span><span class="sets">${setCells(sets)}</span></div>`;
        const courtLine = (m) => (m.court || m.time)
            ? `<div class="mt">${[m.court, m.time].filter(Boolean).join(' · ')}</div>` : '';
        const matchBox = (m) =>
            `<div class="match">${team(m.team1, m.sets1, m.winner === 1)}${team(m.team2, m.sets2, m.winner === 2)}${courtLine(m)}</div>`;

        const treeHtml = (rounds) => {
            let h = '<div class="bracket">';
            rounds.forEach((round, ri) => {
                const last = ri === rounds.length - 1;
                h += `<div class="round${last ? ' is-last' : ''}">${round.title ? `<div class="round-title">${round.title}</div>` : ''}<div class="round-matches">`;
                for (const m of round.matches) h += `<div class="match-slot">${matchBox(m)}</div>`;
                h += '</div></div>';
            });
            return h + '</div>';
        };

        let inner = headerHtml + treeHtml(b.rounds);

        const blocks = [];
        if (b.third) blocks.push({ title: 'Dėl 3 vietos', rounds: [{ title: '', matches: [b.third] }] });
        for (const p of (b.placements || [])) blocks.push(p);
        if (blocks.length) {
            inner += '<div class="placements-row">';
            for (const blk of blocks) {
                inner += `<div class="placement"><div class="placement-title">${blk.title}</div>${treeHtml(blk.rounds)}</div>`;
            }
            inner += '</div>';
        }

        stage.innerHTML = `<div class="bracket-screen"><div class="bracket-fit">${inner}</div></div>`;

        const fit = stage.querySelector('.bracket-fit');
        if (fit) {
            fit.style.transform = 'none';
            requestAnimationFrame(() => {
                const w = fit.scrollWidth, h = fit.scrollHeight;
                if (w && h) {
                    const s = Math.min(1, (window.innerWidth - 80) / w, (window.innerHeight - 80) / h);
                    fit.style.transform = `scale(${s})`;
                }
            });
        }
        return;
    }
```

- [ ] **Step 2: Update the bracket styles** in `@section('styles')`. REMOVE the old
  `.bracket-third { ... }`, `.bracket-third .round-title { ... }`, `.bracket-third .match { ... }`
  rules. Then ADD these rules right after the existing bracket connector rules:

```css
    /* full-screen + auto-fit */
    .bracket-screen { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; }
    .bracket-fit { transform-origin: center center; display: flex; flex-direction: column; align-items: center; gap: 18px; }
    /* court / time caption */
    .match .mt { padding: 2px 13px 7px; font-family: 'Oswald', sans-serif; font-size: 11px;
        letter-spacing: .05em; color: var(--ov-muted); }
    /* placements (3rd place + consolation), visually secondary */
    .placements-row { display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-start; gap: 26px; }
    .placement { display: flex; flex-direction: column; align-items: center; }
    .placement-title { font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .1em; font-size: 12px; color: var(--ov-accent); opacity: .85; margin-bottom: 6px; }
    .placement .bracket { padding: 0; }
    .placement .match { width: 198px; }
    .placement .team { font-size: 13px; padding: 6px 11px; }
```

(Keep all the existing `.bracket`, `.round`, `.round-matches`, `.match-slot`, `.match`, `.team`,
`.sets`, `.g`, and connector rules — they're reused by both the main tree and the placement trees.)

- [ ] **Step 3: Render check**

```
php artisan view:clear
php artisan tinker --execute="\$o=App\Models\Overlay::firstOrCreate(['name'=>'wv2','type'=>'group_standings']); echo app('Illuminate\Contracts\Http\Kernel')->handle(Illuminate\Http\Request::create('/overlay/'.\$o->token,'GET'))->status();"
```
Expected: `200`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/overlays/window.blade.php
git commit -m "feat: full-screen auto-fit bracket with placements and court/time"
```

---

## Done criteria

- `php artisan test` green (apart from the unrelated stock `ExampleTest`).
- Bracket payload carries `placements` and per-match `court`/`time`.
- The overlay renders the main tree + 3rd-place + placement mini-trees full-screen, auto-scaled
  to fit, with court/time captions when present.

## Manual OBS verification (Tadas)

1. Deploy (git pull + clear caches; no migration). Restart `node push.js`.
2. Bracket window on a 16-draw category → confirm the main tree + "Dėl 3/7/11/15 vietos" blocks
   all fit the screen (auto-scaled) and look tidy; court/time appears under matches once the
   organizer schedules them.
