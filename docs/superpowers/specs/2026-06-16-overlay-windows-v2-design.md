# Overlay Windows v2 — Design

**Date:** 2026-06-16
**Status:** Approved design, pending implementation plan
**Builds on:** `2026-06-16-broadcast-overlays-design.md` (v1) + push-model (snapshot ingest)

## Summary

Evolve the broadcast overlay system from a single live category/group toggle into a
**windows (scenes)** model: an overlay holds several pre-built windows, and the operator
plays/stops each one live (with animation, no Save). Also: detect per category whether it
is a group stage or a bracket and guide window creation accordingly; add a configurable
points column and medals; make the logo render; and add full color customization with 5
preset palettes.

## Goals

- One overlay (one OBS URL) holds multiple **windows**; exactly one window is shown at a time.
- Each group-window freely selects subgroups, even across categories; the adaptive grid
  renders them.
- Live control page: per-window **▶ Play / ■ Stop**, applied instantly (no Save button);
  switching windows animates the old one out and the new one in.
- When the bridge reads a tournament, it records per category whether it has **groups**
  and/or a **bracket** (via the `groups` and `draws` queries), so window creation offers
  the right choice.
- Configurable standings columns (place+medal, points, W/L, played) and full color theming
  with 5 presets.
- The logo renders in the panel.

## Non-Goals (YAGNI)

- Automatic bracket match data from the API. The public API hides match participants
  (`entry1/entry2/groupEntry1/groupEntry2/teamMembers` are all null) for group matches, so
  bracket trees cannot be reliably reconstructed. Brackets are **detected** automatically
  but their data is entered **manually** (existing `bracket_data`). Auto-bracket is a future
  investigation.
- SR (set ratio), PR (point ratio), and the head-to-head results grid — not computable,
  same reason (participants hidden). Only the API-derivable columns are offered.
- Multiple windows visible at once; separate output URL per window.

## API findings (constraints)

- `groups(filter:{tournamentCategory})` → round-robin groups with `entries` (place, pair)
  and `matches` (`status`, `score`, `winner.id`). Match participants are null.
- `draws(filter:{tournamentCategory})` → `[JSONObject]`; presence + `segment/type/size`
  indicate an elimination/main draw exists.
- Derivable per pair: place, name, **wins (points)**, losses (when the group is complete),
  played. Not derivable: who beat whom, set/point ratios.

## Data Model

`overlays` table — add one column, repurpose `state`, extend `config`:

| Field            | Change | Shape |
|------------------|--------|-------|
| `windows` (new)  | json   | `[ { "id": "w1", "type": "groups"\|"bracket", "name": "...", "subgroups": [ {"category_id": 53641, "group_id": 21319\|null} ], "bracket_data": {...}\|null } ]` |
| `state`          | change | `{ "active_window_id": "w1"\|null, "next_match": "..." }` |
| `config`         | extend | adds `colors` `{bg,text,accent,muted}`, `theme` (preset key), keeps `logo`, `position`, `title`, `visible_columns` (now may include `points`) |

`group_id: null` in a subgroup = all subgroups of that category.

`overlay_snapshots.payload` — extend with per-category stage detection:

```json
{
  "title": "...",
  "categories": [ {"id":53641,"category":{"id":..,"name":".."},"mde":..} ],
  "category_stages": { "53641": {"has_groups": true, "has_bracket": false, "draw_type": null, "draw_size": null} },
  "groups_by_category": { "53641": [ {group...} ] }
}
```

## Components

1. **Push script** (`tools/overlay-push/push.js`) — for each category also calls
   `draws(...)`; sets `category_stages[cat] = { has_groups: groups.length>0, has_bracket:
   draws.length>0, draw_type, draw_size }`. Still pushes raw groups. No server outbound.

2. **Ingest** (`OverlayController::ingest`) — accepts the extended payload
   (`category_stages` added to validation/storage). Token-protected, unchanged auth.

3. **`OverlayData`** (service) — reads snapshot. New/changed methods:
   - `categoryStages(string $tournamentId): array`
   - `resolveWindow(Overlay $overlay, array $window): array` — gather the window's subgroups
     from the snapshot, compute standings per group, return `groups[]` + `subgroup_count`.
   - `computeStandings(...)` — add `points` (= wins) to each row.

4. **`OverlayController::data`** — instead of active category/group, read
   `state.active_window_id`, find that window in `overlay->windows`, and:
   - `groups` window → `OverlayData::resolveWindow` → groups + subgroup_count (+ stale when
     a selected subgroup has no snapshot data).
   - `bracket` window → render `window.bracket_data`.
   - No active window → `{ visible: false }` equivalent (nothing shown).
   - Returns `colors`, `logo` (absolute URL via `Storage::url`), `columns`, `title`.

5. **`OverlayResource` (Edit/Create form)** — a **Repeater `windows`**:
   - `name`, `type` (Grupės/Bracketas).
   - groups type → Repeater `subgroups`: category Select (options annotated
     "(grupės)/(bracketas)" from `category_stages`) + group Select (from snapshot, "Visi
     pogrupiai" = null).
   - bracket type → `bracket_data` JSON textarea (existing approach).
   - Appearance section: `theme` preset Select (prefills colors) + ColorPickers for
     `colors.bg/text/accent/muted`, `logo`, `position`, `visible_columns` (incl. `points`).

6. **`OverlayControlPage`** — pick an overlay → list its `windows`; each row has
   **▶ Play** and **■ Stop** Livewire actions that set/clear `state.active_window_id`
   immediately (no Save). Highlights the live window. Keeps a `next_match` field + instant apply.

7. **Overlay templates** — `group_standings` renders the active window's groups (adaptive
   grid, unchanged), now with: medal on place 1–3, optional `points` column, CSS variables
   driven by `colors`, and a logo `<img>` in the panel header. Entrance/exit animation on
   window switch (existing `#stage` mechanism — switching `active_window_id` replays intro).

## Color presets (theme)

`theme` key → fills `colors`:
- `gold_night` (default): bg `#111118`, text `#F5F5F0`, accent `#C9A84C`, muted `#9CA3AF`
- `light`: bg `#FFFFFF`, text `#111118`, accent `#C9A84C`, muted `#6B7280`
- `court_blue`: bg `#0B1E3B`, text `#F5F8FF`, accent `#4FA3FF`, muted `#7E93B8`
- `court_green`: bg `#0C2A1F`, text `#F2FBF6`, accent `#34D399`, muted `#79A893`
- `red_black`: bg `#1A0D0D`, text `#FBEDED`, accent `#EF4444`, muted `#A98686`

Selecting a preset fills the ColorPickers; the operator may then override any slot.

## Animation on window switch

The overlay polls `/data`. When `active_window_id` changes between polls, the front-end
treats it as a hide→show: animate the current content out, swap, animate the new window in
(reusing the `#stage` `.in`/`.intro` mechanism). Stop (`active_window_id = null`) animates out.

## Error Handling

- Active window references a subgroup missing from the snapshot → that group is skipped;
  if all are missing → "waiting for data" + `stale: true`. Never blanks/error on screen.
- Window id in `state` no longer exists (window deleted) → treat as nothing shown.
- Missing/old snapshot → stale, last good frame retained.

## Testing

- **Unit** — `computeStandings` includes correct `points`; medal/columns are template-level
  (manual).
- **Feature** — data endpoint with a seeded snapshot + an overlay whose active window
  selects specific subgroups returns the expected groups/subgroup_count; deleted/empty
  window → nothing/stale; bracket window returns its manual rounds.
- **Feature** — ingest stores `category_stages`.
- **Feature** — control Play/Stop sets/clears `active_window_id`.
- **Manual** — OBS: build windows, Play/Stop swaps with animation; themes + logo render;
  adaptive grid for 1/2/4 subgroups.

## Migration / compatibility

Existing overlays use the old `state` (active_category/active_group). v2 introduces
`windows` + `active_window_id`. Since the feature is new and unreleased to real use beyond
testing, the migration adds the `windows` column (nullable, default `[]`) and the controller
reads the new state shape; no data backfill needed.
