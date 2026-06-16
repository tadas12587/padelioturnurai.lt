# Broadcast Overlays — Design

**Date:** 2026-06-16
**Status:** Approved design, pending implementation plan
**Author:** Tadas + Claude

## Summary

Build a self-hosted broadcast overlay system (a focused, mini "overlays.uno") inside
the existing Laravel 12 / Filament 3.3 app. The operator creates and configures
overlays in the admin panel, each overlay exposes a public output URL that is pasted
into OBS as a Browser Source, and a separate control page lets the operator change
what is shown live during a stream.

First version ships two overlay types:

- **Group standings** — driven automatically from the Tournated GraphQL API.
- **Bracket** — manual entry in v1 (API does not document bracket structure).

Both overlay types must **adapt to variable size**: a category may have 2 subgroups or
4 (or more), each with a different number of pairs; a bracket may hold 8 teams or 16
(or other sizes). Layouts scale to the data rather than assuming a fixed count.

## Goals

- Multiple overlays can exist at once, each with its own copy-paste OBS URL.
- Per-overlay visual settings (title, accent color, logo, visible columns, position),
  defaulting to the site's gold/dark theme.
- A live control page (in admin, usable on a phone or second screen) to switch the
  active category/group, toggle visibility, and set a "next match" lower-third.
- Overlays never show an error or blank flash on screen during a broadcast.
- Works on freehosting.lt shared hosting: no websockets, no Node, no Redis, no artisan
  config/route/view cache.

## Non-Goals (v1, YAGNI)

- Full drag-and-drop visual editor.
- A separate "next match" overlay type (it lives as a lower-third element controlled
  from the same control page).
- Automatic bracket data from the Tournated API (manual in v1; API exploration is a
  later, separate step).
- More than two overlay types.

## Constraints (from prior deployment work)

- Shared host, chrooted SSH; no `php artisan config:cache` / `route:cache` / `view:cache`.
- Runtime `Cache` facade (file driver) is fine — it is unrelated to artisan cache commands.
- Tournated API: POST `https://api.tournated.com/graphql`, header
  `Origin: https://play.padel.lt`, no auth. Must be called server-side (CORS). Unknown
  rate limits; recommended no more frequent than ~15 s.
- `entry1`/`entry2` are null in the group query — head-to-head pairings are not available;
  standings are computed from `winner.id` only.

## Architecture (Approach A: DB state + polling)

```
[Tournated GraphQL]  (play.padel.lt)
        ▲  server-side fetch, cached 15–20s
        │
[Laravel: TournatedClient]
        ▲
[Laravel: OverlayController]
   GET /overlay/{token}        → HTML+JS (transparent)
   GET /overlay/{token}/data   → JSON { visible, title, accent, rows[], next_match, stale }
        ▲ polled every ~3s
        │
[OBS Browser Source]  ← paste output URL

[Filament Admin]
   OverlayResource       → CRUD overlays, copy OBS URL
   OverlayControlPage    → writes overlays.state (live)
        │ Livewire save
        ▼
[DB: overlays.state]  ← read fresh (uncached) on every data poll
```

### Live control flow

Operator opens the control page → selects category/group, toggles visible, types the
"next match" text → Livewire writes `overlays.state` immediately → on its next poll
(≤3 s) the overlay reflects the change.

### Why polling

No websockets/SSE are reliably available on the host. OBS overlays conventionally poll.
Server-side caching means many frequent polls collapse into at most one upstream call per
category per 15–20 s, protecting against unknown API limits.

## Data Model

New table `overlays`:

| Column                   | Type      | Notes                                                        |
|--------------------------|-----------|--------------------------------------------------------------|
| `id`                     | bigint    | PK                                                           |
| `name`                   | string    | Operator-facing label                                        |
| `type`                   | string    | `group_standings` \| `bracket`                               |
| `token`                  | string    | Unique, random, unguessable; used in public URL              |
| `tournament_external_id` | string?   | Tournated tournament id (e.g. 10229); used for group_standings |
| `config`                 | json      | accent_color, logo path, title, visible_columns[], position  |
| `state`                  | json      | active_category_id, active_group_id, visible, next_match     |
| `bracket_data`           | json?     | Manually entered draw (bracket type only)                    |
| `timestamps`             |           |                                                              |

- `token` unique-indexed.
- `config` and `state` always default to sane values so a freshly created overlay renders.

## Components

1. **`OverlayResource`** (Filament) — create/edit/delete overlays. Form exposes name,
   type, tournament id, and `config` fields. List view has a "Copy OBS URL" action
   showing `{APP_URL}/overlay/{token}`.

2. **`OverlayControlPage`** (Filament custom Livewire page) — pick an overlay; for
   `group_standings`, choose category (loaded from Tournated) and group, toggle visible,
   edit the next-match lower-third; for `bracket`, toggle visible (and optionally
   highlight the active match). Writes to `overlays.state`.

3. **`TournatedClient`** (service, `app/Services/TournatedClient.php`) — server-side
   GraphQL calls (`tournamentCategories($tournamentId)`, `groups($categoryId)`),
   `Cache::remember(...)` ~15–20 s, defensive null-safe parsing, and `computeStandings()`
   (PHP port of the documented `calcStats` — wins from `winner.id`, round-robin
   losses/played when all matches complete, pair-name formatting).

4. **`OverlayController`** (public, `app/Http/Controllers/OverlayController.php`) —
   `show($token)` renders the Blade template by type; `data($token)` returns the JSON
   payload (fresh `state` + cached computed data + `stale` flag).

5. **Blade overlay templates** (`resources/views/overlays/{group_standings,bracket}.blade.php`)
   — transparent background, no site chrome, inline minimal JS that polls the data
   endpoint every ~3 s and updates the DOM with CSS fade/slide transitions. Honors
   `config` (accent color, title, columns, position) and `state.visible`.

### Adaptive layouts

Both templates render to the data, not to a fixed shape:

- **Group standings** — a category can expose 2, 4, or more subgroups, each with a
  varying number of pairs. The control page can target a single subgroup or "all
  subgroups"; when showing multiple, the layout flows them into a responsive grid
  (e.g. 2-up / 4-up) and shrinks row density as count grows, so it stays readable on a
  1080p canvas without overflowing.
- **Bracket** — the draw size is derived from the entered `bracket_data` (8, 16, or
  other). The number of rounds and column widths are computed from that size; an 8-team
  draw shows fewer rounds than a 16-team draw without template changes.

The data endpoint includes whatever counts the template needs (subgroup count, draw
size) so the front-end can pick the right layout class.

## Routes

```php
// Public, no locale prefix, outside the setlocale group
Route::get('/overlay/{token}',      [OverlayController::class, 'show'])->name('overlay.show');
Route::get('/overlay/{token}/data', [OverlayController::class, 'data'])->name('overlay.data');
```

No rate-limit middleware on these (OBS polls them continuously); upstream protection is
the server-side cache.

## Error Handling

On-screen stability is the priority — an overlay must never flash an error or go blank
because of a transient upstream problem.

- **Tournated timeout/failure** → return last good cached data with `stale: true`; overlay
  keeps showing the last good state. Upstream timeout kept short (~5 s) so polling never hangs.
- **API shape change** → null-safe parsing everywhere; log once; fall back to last good data.
- **Invalid/unknown token** → 404.
- **`visible = false`** → data returns `{ visible: false }`; JS smoothly hides content,
  leaving OBS transparent.
- **Empty group / no data yet** → overlay shows a tidy "waiting for data" state, not an error.

## Testing

- **Unit** — `TournatedClient::computeStandings()` against the documented U10 fixture
  (wins/losses/places). `Http::fake()` for the GraphQL call; the real API is not hit.
- **Feature** — `/overlay/{token}/data` for a seeded overlay returns the expected JSON
  shape; bad token → 404; `visible=false` → hidden payload.
- **Manual** — final verification in a real OBS Browser Source (requires OBS; done by Tadas).

## Future / Follow-ups

- Investigate a Tournated bracket (main-draw elimination) query; if it reliably returns
  the draw, wire bracket overlays to the API with manual override.
- Optional per-team logos and richer lower-third styling.
- Possible additional overlay types (schedule, sponsor ticker).
