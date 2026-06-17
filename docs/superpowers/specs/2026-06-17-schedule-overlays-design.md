# Schedule (Order of Play) Overlays — Design

**Date:** 2026-06-17
**Status:** Approved design, pending implementation plan
**Builds on:** bracket/groups overlays + push snapshot model

## Summary

A new **"Tvarkaraštis"** (schedule) overlay window driven by the tournament's
matches. One window type with four **variants**:

- **Pagal kortą** (by court) — columns per court, each listing its matches by time.
- **Pagal laiką** (by time) — grouped by time slot, each row a court + match.
- **Dabar žaidžiama** (now playing) — matches flagged in progress.
- **Toliau** (next/upcoming) — pending matches, soonest first.

Same architecture as existing overlays: the push bridge sends raw matches, the
**server** filters/sorts/groups per the window's variant, the renderer draws.

## Data source

Tournated GraphQL `matches(filter: { tournament: <id> })` exposes everything
needed (confirmed against tournament 10424). Per match we keep a compact shape:

```json
{
  "id": 1148450,
  "date": "2026-04-18",
  "time": "12:00",
  "duration": 60,
  "court": "Court 7",
  "court_id": 49934,
  "category_id": 53641,
  "category": "Vyrai 35+",
  "status": "completed",
  "in_progress": false,
  "round": "R1",
  "segment": "main",
  "score": "6:3 2:6 [10:8]",
  "team1": ["Paulius Lavrukaitis", "Neividas Biriukovas"],
  "team2": ["Marius Linartas", "Tomas Vitkus"],
  "winner": 1
}
```

- `date` is the `date` field's calendar day (it arrives as midnight UTC).
- `team1`/`team2` from `participant1/2.users[].{name surname}` (joined "Name Surname").
- `winner` is 1, 2, or null, resolved from the match `winner`/result.
- `segment` is `bracketType` (e.g. `main`, `5-8`, `9-16`) — passthrough, may be null.

## Push (`tools/overlay-push/push.js`)

Add `fetchMatches(tournamentId)` (one query per tournament) and a `normalizeMatch`
helper producing the shape above. Add the resulting `matches` array to the
snapshot alongside `groups_by_category` / `brackets_by_category`. ~100 matches
per tournament — small.

## Server

### Ingest (`OverlayController::ingest`)
Accept and store an optional `matches` array (validation: `matches` => array).

### Resolver (`OverlayData::resolveSchedule(string $tid, array $window): array`)
1. Start from snapshot `matches`.
2. Apply window filters when set: `date` (exact day), `category_ids` (in list),
   `courts` (court_id in list).
3. Branch on `schedule_variant`:
   - `by_court`: group by court → `[{ court, matches: [...] (sorted by time) }]`.
   - `by_time`: group by time → `[{ time, matches: [...] (court+teams) }]` (sorted).
   - `now`: keep `in_progress === true`, sort by court then time, cap at `limit`.
   - `next`: keep `status === 'pending'`, sort by time, cap at `limit`.
4. Return `{ variant, groups | items }` — `by_court`/`by_time` return `groups`
   (each with a heading + matches); `now`/`next` return a flat `items` list.

Empty result (no matches after filters) returns empty groups/items — the renderer
shows an empty-state message; the data endpoint still reports `visible: true`.

### Court options (`OverlayData::courts(string $tid): array`)
Distinct `court_id => court` from snapshot matches, for the admin multi-select.

### Data endpoint (`OverlayController::data`)
New branch: `type === 'schedule'` → `$payload['schedule'] = resolveSchedule(...)`
and `$payload['schedule_variant'] = $window['schedule_variant']`.

## Admin (`OverlayResource`)

Window `type` options gain `'schedule' => 'Tvarkaraštis'`. Fields visible only
when `type === 'schedule'`:

- `schedule_variant` Select: `by_court` / `by_time` / `now` / `next`.
- `date` DatePicker (the broadcast day).
- `category_ids` multi-select (options from `OverlayData::categories`).
- `courts` multi-select (options from `OverlayData::courts`).
- `limit` numeric (default 6; applies to `now`/`next`).

Reuse existing live/option patterns. `schedule_variant` is a dedicated key so it
never collides with the sponsors `variant` field.

## Renderer (`resources/views/overlays/window.blade.php`)

New `schedule` branch with four layouts, all theme-driven (`--ov-*`, Oswald/Barlow):

- **by_court** — a row of court columns; each column header = court name, rows =
  `time · category` and the two team names (winner emphasised), score when present.
- **by_time** — sections by time; each row = court · category · teams · score.
- **now** — prominent list, each item highlighted (accent), showing court · category
  · teams · live score.
- **next** — list of upcoming items: time · court · category · teams.

Empty state: "Nėra suplanuotų rungtynių". Missing court → "—".
`by_court`/`by_time` can be wide → use a full-screen centered container (like the
bracket); `now`/`next` honour the window `position`.

## Testing

Feature tests (`tests/Feature/OverlayEndpointTest.php`):
- Ingest stores `matches` (round-trips through the data endpoint).
- `by_court` groups matches by court, sorted by time.
- `now` keeps only `in_progress`; `next` keeps only `pending`, sorted by time.
- `category_ids` / `courts` / `date` filters narrow results; `limit` caps `now`/`next`.

## Error handling / compatibility

- No `matches` in snapshot (older push) → schedule windows resolve to empty state.
- Snapshot/window JSON are free-form → no migration.
- Existing groups/bracket/sponsors windows are untouched.

## Out of scope (YAGNI)

- Auto "today" date (admin picks the date).
- Time-based "now/next" computation (uses API status only).
- Live current-time indicators / countdowns.
