# Bracket Placements + Court/Time + Auto-Fit — Design

**Date:** 2026-06-16
**Status:** Approved design, pending implementation plan
**Builds on:** automatic brackets (`2026-06-16-auto-brackets-design.md`)

## Summary

Two data additions to brackets — **court/time on matches** and **placement sub-brackets**
(for 7th/11th/15th place) — presented in a **full-screen, auto-fitted** bracket layout so
everything is shown at once and always fits the canvas at a readable, sharp size.

## Data (snapshot)

A bracket match gains optional `court` and `time`:

```json
{ "team1": "...", "team2": "...", "sets1": "6 6", "sets2": "2 3", "winner": 1, "court": "Kortas 2", "time": "10:00" }
```

The category bracket gains `placements`:

```json
"bracket": {
  "rounds":     [ { "title": "1/8 finalis", "matches": [match, ...] }, ... ],   // main draw
  "third":      match | null,                                                    // 3rd place
  "placements": [ { "title": "Dėl 7 vietos", "rounds": [ { "title": "", "matches": [match] } ] }, ... ]
}
```

## Push parsing (`tools/overlay-push/push.js`)

In `extractBracket(draw)`:

- **Court/time** in `matchOf`: `court = seed.court?.name ?? (typeof seed.court === 'string' ? seed.court : null)`;
  `time = seed.time || null`. (Both usually `null` until the organizer schedules; rendered only when present.)
- **Placements**: after the main rounds and the 3rd-place round are taken, group the
  remaining rounds into placement brackets. Walk the leftover rounds in order, accumulating;
  when a round's title matches `/(\d+)\D*place/i` (e.g. "7th place"), it closes the current
  placement bracket: `{ title: "Dėl <N> vietos", rounds: [...accumulated, thisRound] }`, then
  reset the accumulator. The 3rd-place round is excluded (already used as `third`). Internal
  placement round titles are left blank (the block title carries the meaning).
- `extractBracket` returns `{ rounds, third, placements }`.

## Server

No change to `OverlayData::bracketForCategory` beyond passing through `placements` (return
`'placements' => $b['placements'] ?? []` alongside `rounds`/`third`).

## Renderer (overlay `window.blade`, bracket branch)

- **Full-screen container.** The bracket renders into a fixed full-viewport centered wrapper
  (`position: fixed; inset: 0; display:flex; align-items/justify center`), overriding the
  corner `position` (a bracket is a full-screen graphic, like the sponsors fullscreen variant).
- **Layout** inside a `.bracket-fit` wrapper:
  - optional header (tournament logo + title),
  - the main `.bracket` tree (existing tournament-tree styling + connectors + winner accent),
  - a `.placements-row` (flex-wrap) holding the **3rd place** block first, then each
    **placement** block — compact mini-trees with a muted title, visually secondary so the
    main tree dominates.
- **Court/time** per match: a small muted caption line under the two teams, shown only when
  `court` or `time` is set: `Kortas 2 · 10:00`.
- **Auto-fit scaling.** After building the markup, measure `.bracket-fit`'s natural size and
  apply `transform: scale(s)` (transform-origin center) where
  `s = min(1, (innerWidth - margin) / width, (innerHeight - margin) / height)` (margin ~80px).
  This guarantees any draw size (8/16, with or without placements) fits the canvas at the
  largest sharp size. The scale lives on `.bracket-fit`; the existing `#stage` intro animation
  (opacity/translate) is unaffected.

## Error handling

- No placements → `.placements-row` omitted (only main tree + optional 3rd place).
- Missing court/time → caption omitted.
- Empty bracket → nothing shown (existing behavior).
- Auto-fit with zero measured size (not yet laid out) → skip scaling that frame; re-measured
  on the next render.

## Testing

- **Feature** — data endpoint returns `bracket.placements` and per-match `court`/`time` when
  present in the snapshot; absent when not. (Seed a snapshot with placements + court/time.)
- **Manual (push)** — Node validation against a live draw confirms placements parse into
  "Dėl N vietos" blocks and court/time pass through when present.
- **Manual (OBS)** — a 16-draw renders main tree + 3rd place + placement blocks, all fitting
  the screen via auto-fit; an 8-draw renders larger; court/time captions appear when scheduled.

## Migration / compatibility

No schema change (snapshot/window JSON are free-form). Existing bracket windows keep working;
they gain placements/court-time automatically once the updated push runs.
