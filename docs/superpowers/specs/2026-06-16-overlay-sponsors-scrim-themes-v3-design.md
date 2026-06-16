# Overlay v3 — Sponsors, Background Scrim, More Themes — Design

**Date:** 2026-06-16
**Status:** Approved design, pending implementation plan
**Builds on:** overlay windows v2 (`2026-06-16-overlay-windows-v2-design.md`)

## Summary

Three additions to the overlay system:

- **A. Background scrim** — a per-window full-screen themed dim layer (with adjustable
  opacity %) that animates in/out with the window, so the live feed recedes and the
  graphic pops. (True video blur is impossible from a browser source — see Constraints.)
- **B. More color themes** — expand from 5 to ~11 professionally-curated palettes.
- **C. Sponsors overlay** — a new window type `sponsors` with three variants (corner
  rotating logos, bottom bar with name + URL, full-screen themed), sourced from the
  existing `Sponsor` records and/or bulk-uploaded images, with high-quality animations.

## Constraints

- An OBS Browser Source cannot blur or read other OBS sources (the video sits on a
  separate layer; CSS `backdrop-filter` only affects pixels within the same browser
  source). So "blur background" is implemented as a **themed translucent scrim** (dim/tint)
  with an opacity %, not a literal blur of the video. Literal video blur would be an OBS
  filter the operator applies manually.
- Same host constraints as before (no artisan cache commands; data via snapshot push).

## A. Background scrim

Each window gains: `scrim_enabled` (bool) and `scrim_opacity` (0–100, default 55).

- The overlay shell renders a full-viewport `#scrim` layer behind all content, colored
  with the theme background (`--ov-bg`) at `scrim_opacity%`, fading in when a window with
  `scrim_enabled` becomes active and fading out on stop/disable.
- The data payload exposes `scrim: { enabled: bool, opacity: int }` for the active window.

## B. Color themes

`Overlay::themePresets()` grows to ~11 entries; each is `{ label, colors: {bg,text,accent,muted} }`.
Curated for broadcast (high contrast, brand-driven). Existing kept: gold_night, light,
court_blue, court_green, red_black. New: midnight (navy/cyan), graphite (charcoal/mono),
wine_gold (maroon/gold), esports_purple, orange_energy (black/orange), ice_blue (light).
Final hex values fixed in the plan.

## C. Sponsors overlay

### Data model (window)

A window may be `type: 'sponsors'` with:
- `variant`: `corner` | `bar` | `fullscreen`
- `sponsor_ids`: array of `Sponsor` ids selected from the existing list
- `images`: array of uploaded image paths (bulk drag-drop)
- `rotate_seconds`: int (default 6)

`Sponsor` model already exists: `name, logo, url, category, is_active`.

### Resolution (`OverlayData::resolveSponsors`)

Build an ordered `items` list:
- For each `sponsor_ids` (active sponsors): `{ logo: Storage::url(logo), name, url }`.
- For each `images` path: `{ logo: Storage::url(path), name: null, url: null }`.
Selected sponsors first, then uploaded images.

### Data payload (sponsors window)

`{ visible, window_id, window_type:'sponsors', variant, rotate_seconds, items:[{logo,name,url}], colors, scrim, ... }`

### Variants (renderer)

- **corner** — small panel positioned per `config.position`; shows one item at a time;
  cross-fades to the next every `rotate_seconds`.
- **bar** — full-width bottom bar: logo + name + URL (when present); rotates per item.
- **fullscreen** — full-viewport themed gradient (from `--ov-bg`/`--ov-accent`), large
  centered logo; elegant fade+scale between items. (Best for breaks between matches.)

Rotation runs inside the overlay (its own timer), independent of the 3 s data poll.

### Admin (window editor)

When a window's type is `sponsors`, the repeater shows: `variant` select,
`sponsor_ids` multiselect (from active `Sponsor` records), `images` FileUpload
(`->multiple()`, bulk), `rotate_seconds`. Scrim fields (`scrim_enabled`, `scrim_opacity`)
appear for every window type. Group/bracket fields hidden.

### Control

Play/Stop per window already generic — sponsors windows play/stop the same way.

## Renderer changes (shared shell)

- Add a full-viewport `#scrim` layer; toggle/opacity from payload, fade with the window.
- **Signature-based re-render:** the poll currently re-renders every 3 s; change it to
  re-render only when the window changes OR the payload content changes (compare a JSON
  signature). This keeps sponsor rotation timers alive between polls and avoids flicker
  for static content, while still updating live group scores when they change.
- Sponsors render path sets up a rotation interval cleared on each (re)render.

## Error handling

- Sponsors window with no items → nothing shown (treated as not visible / empty), no error.
- Missing sponsor logo / broken image path → item skipped.
- Scrim with opacity 0 or disabled → no scrim layer.
- Unknown window/window deleted → nothing shown (existing behavior).

## Testing

- **Unit/Feature** — `OverlayData::resolveSponsors` returns items from ids + images in
  order; inactive sponsors excluded; logos resolved to URLs.
- **Feature** — data endpoint for a sponsors window returns `variant`, `rotate_seconds`,
  `items`; payload includes `scrim` for the active window.
- **Feature** — existing group/bracket endpoints unaffected.
- **Manual (OBS)** — each variant renders and rotates; scrim dims the feed at the chosen
  %; themes apply; animations smooth.

## Migration / compatibility

No new tables. `windows` JSON already flexible (new keys per window). `config` gains the
expanded theme list (code-level). Existing overlays keep working (new fields default off).
