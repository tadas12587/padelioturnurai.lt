# Bracket Auto-Advance + Tennis Scores — Design

**Date:** 2026-06-16
**Status:** Approved design, pending implementation plan
**Builds on:** bracket builder (`2026-06-16-bracket-builder-design.md`)

## Summary

Three bracket improvements:
1. **Tennis-format scores** — per match, each team has its set games (e.g. team1 "6 6",
   team2 "2 3"), shown as game columns per team row like a real tennis bracket.
2. **Auto-advance** — the operator fills only round 1 (pairs) and marks each match's winner;
   the server derives all later rounds (winner moves into the next round's slot) and the
   3rd-place match from the semifinal losers.
3. **Winner highlighting** — the winning team is marked (accent) — already implemented;
   confirmed and carried through the derived rounds.

## Data model (`bracket_data` per window)

Flat match list (unchanged container), match fields:

```json
{ "round": "Ketvirtfinaliai", "team1": "", "team2": "", "sets1": "", "sets2": "", "winner": null }
```

- `sets1`/`sets2`: space-separated set games for each team, e.g. `"6 6"` / `"2 3"`.
  (Replaces the previous `score1`/`score2`.)
- `winner`: `1` | `2` | `null`.
- Team names are entered only for round 1; later rounds are derived (may be overridden if a
  non-empty name is stored).

`Overlay::bracketSkeleton()` updated to emit `sets1`/`sets2` (empty) instead of score fields.

## Auto-advance (server-side derivation in `OverlayController::buildBracket`)

1. Group the flat matches into ordered main rounds (preserving first-seen order), and pull
   out the `Dėl 3 vietos` match as `third`.
2. Derive names round by round:
   - Round 0: names as stored.
   - Round r>0, match i: `team1 = winnerName(prevRound.matches[2i])`,
     `team2 = winnerName(prevRound.matches[2i+1])` — using the already-derived previous
     round, so winners cascade. A non-empty stored name overrides the derived one.
   - `winnerName(m) = m.winner===1 ? m.team1 : (m.winner===2 ? m.team2 : '')`.
3. 3rd place: the round before the final (`rounds[count-2]`, the semifinals) supplies the
   two **losers**: `third.team1 = loserName(sf.matches[0])`,
   `third.team2 = loserName(sf.matches[1])` (stored override allowed).
   `loserName(m) = m.winner===1 ? m.team2 : (m.winner===2 ? m.team1 : '')`.

Output (unchanged shape, with sets): `bracket = { rounds:[{title, matches:[{team1,team2,sets1,sets2,winner}]}], third }`.

## Builder (Filament window editor, bracket type)

- `Select size` (8/16) → generates the skeleton (now with `sets1`/`sets2`).
- `Repeater bracket_data.matches` (fixed): per match —
  `team1`, `team2` (TextInput), `sets1`, `sets2` (TextInput, label "Setai, pvz. 6 6"),
  `winner` (Select 1/2). Section/helper text: "Pildyk tik 1-ą raundą — vėlesni užsipildo
  automatiškai pagal nugalėtojus."

## Rendering (overlay `window.blade` bracket branch)

- Each team row: name on the left, set games on the right rendered as fixed cells
  (split `sets1`/`sets2` on whitespace), aligned between the two teams.
- Winner team highlighted in the accent (existing `.team.win`).
- Same tournament-tree layout + connectors; derived names flow toward the final.

## Error handling

- Unmarked winner → next slot stays "TBD"; nothing breaks.
- Empty sets → no game cells shown.
- Fewer than 3 rounds (shouldn't happen via skeleton) → derivation guards array bounds;
  no 3rd place if no semifinal round.

## Testing

- **Unit** — `bracketSkeleton` items contain `sets1`/`sets2` (not score fields).
- **Feature** — `buildBracket`: given round-1 names + winners, round 2 names are the round-1
  winners in the correct slots; the final shows the semifinal winners; `third` shows the two
  semifinal losers; sets pass through.
- **Manual (OBS)** — fill an 8-draw round 1 + winners + sets; confirm later rounds auto-fill,
  3rd place shows SF losers, set columns render, winners highlighted.

## Migration / compatibility

No schema change. Renderer + builder switch from `score1`/`score2` to `sets1`/`sets2`;
brackets are entered manually and not yet in production use, so no data migration.
