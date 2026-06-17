# Automatic Brackets (from Tournated draws) — Design

**Date:** 2026-06-16
**Status:** Approved design, pending implementation plan
**Supersedes:** the manual bracket builder + auto-advance (`bracket-builder`,
`bracket-autoadvance-tennis` specs). Manual bracket entry is removed.

## Summary

Brackets are populated automatically from the Tournated `draws` query (which exposes the
full main draw: rounds, matches, pairs, winners, scores) — the same push/snapshot pipeline
used for groups. The manual bracket builder, skeleton generator, and winner auto-advance
logic are removed. A bracket window now just points at a category; the overlay renders that
category's draw.

## Key finding

`draws(filter:{tournamentCategory})` returns, per draw: `rounds[]` where each round has
`title` and `seeds[]` (matches). Each match has `teams[]` (each team → `users[].user.name`
/`surname`), `winner` (a team object whose `id` matches one of the match's `teams[].id`),
and `addScore.addScore` (score string, e.g. `"6:2 6:0"`). A `3rd place` round follows the
`Final`. "play-each-place" draws also contain placement brackets (7th/11th/15th place) which
we ignore.

## Push script (`tools/overlay-push/push.js`)

For each category, fetch `draws` and build a normalized bracket from the **main draw only**:

1. **Main rounds** = walk `rounds` from the start while each round's match count halves
   (N, N/2, …, 1); stop after the round with 1 match (the final). This yields
   R1→…→Final and excludes the placement brackets that follow.
2. **Third place** = the first subsequent round whose `title` matches `/3rd/i` (one match).
3. **Round titles (LT)** derived from match count: 16→"1/16 finalis", 8→"1/8 finalis",
   4→"Ketvirtfinaliai", 2→"Pusfinaliai", 1→"Finalas". (Independent of the API's English titles.)
4. **Per match**: `team1`/`team2` = pair names (`users` → "Name Surname / Name Surname");
   `winner` = `1` if `winner.id === teams[0].id`, `2` if `=== teams[1].id`, else `null`;
   set games per team parsed from `addScore.addScore`: split on whitespace; each set token
   split on `:` (strip `[]`), left → team1 game, right → team2 game; join with spaces into
   `sets1`/`sets2`. (Score is read team1-first = `teams[0]` left.)

Add `brackets_by_category` to the pushed snapshot:

```json
"brackets_by_category": {
  "<categoryId>": {
    "rounds": [ { "title": "1/8 finalis", "matches": [ {team1,team2,sets1,sets2,winner} ] } ],
    "third":  { team1,team2,sets1,sets2,winner } | null
  }
}
```

Empty/absent when the category has no main draw.

## Ingest

`OverlayController::ingest` validation + stored payload gain `brackets_by_category` (array).

## Server read + render

- `OverlayData::bracketForCategory(string $tournamentId, int $categoryId): array` returns the
  stored `brackets_by_category[categoryId]` or `['rounds' => [], 'third' => null]`.
- `OverlayController::data` bracket branch becomes:
  `$payload['bracket'] = $data->bracketForCategory($overlay->tournament_external_id, $window['category_id'])`.
- The overlay renderer is **unchanged** — it already renders `d.bracket.{rounds,third}` with
  per-team set columns and winner highlighting (tournament-tree styling).

## Admin (window editor)

Bracket window fields become just:
- `Select category_id` — categories that have a bracket (from `category_stages` where
  `has_bracket`), labeled by name.

Removed from the bracket window: `bracket_data.size` select, the `bracket_data.matches`
repeater, and the auto-advance save hooks.

## Removed code (cleanup)

- `Overlay::bracketSkeleton()`, `Overlay::advanceBracket()`.
- `OverlayController::buildBracket()` (replaced by `bracketForCategory`).
- `EditOverlay::mutateFormDataBeforeSave` + `afterSave`, `CreateOverlay::mutateFormDataBeforeCreate`,
  and `OverlayResource::advanceBracketWindows()`.
- Tests: `BracketSkeletonTest`, `AdvanceBracketTest`, and the manual-bracket endpoint test.

## Error handling

- Category has no draw / push hasn't run → `bracket.rounds` empty → overlay shows nothing
  for that window (no error).
- Match with no winner → no highlight; "TBD" for missing pair names.
- Odd score strings → best-effort parse; unparseable set tokens are skipped.

## Testing

- **Feature** — seed a snapshot with `brackets_by_category` for a category; a bracket window
  pointing at it returns `bracket.rounds` (correct titles/order) + `third`, with sets and
  winner. Empty category → empty bracket.
- **Manual (OBS)** — run `push.js` against a real tournament with a draw; confirm the bracket
  renders with live pairs/scores/winners and updates as results change.
- The push parsing is validated manually against the live `draws` payload (Node script).

## Migration / compatibility

No schema change. Existing manually-built bracket windows lose their manual data; operators
re-select the category. Brackets not yet in production use.
