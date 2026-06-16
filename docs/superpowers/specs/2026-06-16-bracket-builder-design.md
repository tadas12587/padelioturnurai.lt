# Bracket Builder — Design

**Date:** 2026-06-16
**Status:** Approved design, pending implementation plan
**Builds on:** overlay windows v2/v3

## Summary

Replace the raw-JSON bracket entry with an easy admin builder: the operator picks a draw
size (8 or 16), the system generates the correct round skeleton (including the 3rd-place
match), and the operator fills in pair names, scores, and winners. The overlay renders the
bracket grouped into round columns plus a distinct 3rd-place block, themed by the existing
color variables. Bracket data is entered manually (the API does not expose draw matchups).

## Goals

- Pick **8** or **16** → auto-generate the round skeleton; operator only fills values.
- Always include the **3rd-place** match.
- Flat, robust admin editing (no deep nested repeaters).
- Overlay renders rounds left→right with a separate 3rd-place block; matches the theme.

## Data model (`bracket_data` per window)

A flat match list (round given as a label), which is easy to edit and group:

```json
{
  "size": 8,
  "matches": [
    { "round": "Ketvirtfinaliai", "team1": "", "score1": "", "team2": "", "score2": "", "winner": null },
    ...
    { "round": "Pusfinaliai", ... },
    { "round": "Finalas", ... },
    { "round": "Dėl 3 vietos", ... }
  ]
}
```

- `winner`: `1` | `2` | `null` (which team won).
- Round labels (LT): `1/8 finalio`, `Ketvirtfinaliai`, `Pusfinaliai`, `Finalas`, `Dėl 3 vietos`.

### Skeleton sizes

- **8:** Ketvirtfinaliai (4) → Pusfinaliai (2) → Finalas (1) + Dėl 3 vietos (1) = 8 matches.
- **16:** 1/8 finalio (8) → Ketvirtfinaliai (4) → Pusfinaliai (2) → Finalas (1) + Dėl 3 vietos (1) = 16 matches.

A pure helper `Overlay::bracketSkeleton(int $size): array` returns the `matches` array
(empty teams). Used by the admin generator.

## Builder UX (Filament, bracket window)

- `Select bracket_data.size` options 8/16, `->live()`. `afterStateUpdated` sets
  `bracket_data.matches` to `Overlay::bracketSkeleton($size)` (only when empty or the size
  changed, so it doesn't wipe filled data unexpectedly — confirm before regenerate is out of
  scope; regenerating on size change is acceptable since size rarely changes mid-fill).
- `Repeater::make('bracket_data.matches')` — `addable(false) deletable(false)
  reorderable(false)` (structure fixed by size). Each item:
  - round shown as a read-only label / item label,
  - `team1`, `team2` (TextInput), `score1`, `score2` (TextInput), `winner`
    (Select: `1` => '1-as', `2` => '2-as', null => '—').
  - `itemLabel` shows the round.
- Visible only when window `type === 'bracket'`. Replaces the old raw-JSON textarea.

## Rendering

The data endpoint groups the flat `matches` into ordered rounds and separates the
3rd-place match, returning for a bracket window:

```
bracket: {
  rounds: [ { title, matches: [ {team1,score1,team2,score2,winner} ] } ],  // main rounds, in order
  third:  { team1,score1,team2,score2,winner } | null
}
```

Grouping preserves first-seen round order; the round titled `Dėl 3 vietos` becomes `third`.

The overlay (`window.blade` bracket branch) renders `bracket.rounds` as columns (match boxes
with two teams; winner highlighted in the accent color, score shown), and renders `bracket.third`
as a separate labeled block ("Dėl 3 vietos"). Themed via `--ov-*`. Uses the existing
entrance animation (`.round` stagger).

## Error handling

- Empty bracket (no filled teams) → renders the empty skeleton boxes (TBD placeholders) or,
  if no matches at all, shows nothing — never errors.
- `winner` null → neither team highlighted.
- Missing 3rd-place match → omit the block.

## Testing

- **Unit** — `Overlay::bracketSkeleton(8)` and `(16)` return the documented match counts and
  round labels in order (incl. the 3rd-place match).
- **Feature** — data endpoint for a bracket window groups flat matches into `bracket.rounds`
  (correct order) and extracts `bracket.third`; a window with no matches → no bracket payload
  / not visible content.
- **Manual (OBS)** — build an 8 and a 16 bracket, fill some results, confirm columns + 3rd
  place render and theme correctly with animation.

## Migration / compatibility

No schema change (`windows` JSON is free-form). Existing bracket windows using the old
`rounds[].matches[].teams[]` shape are superseded; since brackets were only ever entered
manually and not yet used in production, no data migration is needed — the renderer switches
to the new `bracket` payload shape.
