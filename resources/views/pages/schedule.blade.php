@php
    $tName = $tournament['name'] ?? 'Turnyras';
    $tDate = $tournament['date'] ?? null;
    $hasClubs = $clubList->isNotEmpty();
    $draws = $draws ?? [];
    $hasDraws = count($draws) > 0;
    // Player groups get their own tables; a club league's single group is the club table.
    $hasGroupTables = ! $hasClubs && collect($groups)->contains(fn ($g) => ! empty($g['match_ids']));
    // User's naming: "Grupės" = club table / player groups, "Lentelės" = knockout brackets.
    $hasGroupsTab = $hasClubs || $hasGroupTables;
@endphp
<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $tName }} – grafikas ir rezultatai</title>
@php
  $seoDesc = $tName . ' – mačų grafikas, rezultatai' . ($hasGroupsTab ? ', grupės' : '') . ($hasDraws ? ', tinkleliai' : '') . ' gyvai'
    . ($stats['courts'] ? ' · ' . $stats['courts'] . ' kortai' : '') . ($stats['matches'] ? ' · ' . $stats['matches'] . ' mačai' : '') . '.';
@endphp
<meta name="description" content="{{ $seoDesc }}">
<link rel="canonical" href="{{ route('schedule.show', $tournamentId) }}">
<meta property="og:title" content="{{ $tName }} – grafikas ir rezultatai">
<meta property="og:description" content="{{ $seoDesc }}">
<meta property="og:url" content="{{ route('schedule.show', $tournamentId) }}">
<meta property="og:type" content="website">
<meta name="theme-color" content="#0A2226">
<link rel="manifest" href="{{ route('push.manifest', $tournamentId) }}">
<link rel="apple-touch-icon" href="{{ route('push.icon', 192) }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Grafikas">
{{-- iPhone Safari's own "Padelio turnyrai – Atidaryti/Gauti" bar at the top --}}
<meta name="apple-itunes-app" content="app-id=6833871129">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap">
<style>
  /*
    Padel-court palette: a deep "under the lights" court teal, with a single
    padel-ball yellow-green as the one accent — it always means "live /
    active / yours / winning", never decoration. Club identity is a separate,
    deliberately multi-hue system (CLUB_COLORS in the script) because telling
    clubs apart at a glance is the actual usability problem here.

    Times: a "~" time is the automatic estimate (CourtDelays on the server);
    the planned Tournated time is shown small next to it. No "+17 min"
    figures — people read the time they should be there, not arithmetic.
  */
  :root {
    --ground: #0A2226;
    --surface: #0E2C30;
    --surface-2: #123539;
    --line: rgba(238,244,242,0.09);
    --ink: #EAF3F1;
    --ink-soft: #A9C4BE;
    --muted: #8BA7A1;
    --ball: #D9F45A;
    --ball-ink: #17230a;
    --warn: #F5B54A;
    --sans: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --display: 'Archivo', var(--sans);
  }
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html { scroll-behavior: smooth; }
  body {
    margin: 0; background: var(--ground); color: var(--ink); font-family: var(--sans);
    -webkit-font-smoothing: antialiased; padding-bottom: calc(84px + env(safe-area-inset-bottom));
  }
  a { color: inherit; }
  button { font: inherit; }
  .wrap { max-width: 620px; margin: 0 auto; padding: 0 16px; }

  /* ---------- compact header ---------- */
  header.top {
    padding: 14px 0 12px; display: flex; justify-content: space-between; align-items: center; gap: 12px;
  }
  .brand { font-family: var(--display); font-weight: 700; font-size: 0.9rem; line-height: 1.25; min-width: 0; margin: 0; }
  .brand small { display: block; font-family: var(--sans); font-weight: 500; font-size: 0.8rem; color: var(--muted); margin-top: 2px; }
  .fresh { display: flex; align-items: center; gap: 6px; font-size: 0.74rem; color: var(--muted); white-space: nowrap; flex: none; }
  .fresh .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--ball); box-shadow: 0 0 0 3px rgba(217,244,90,0.18); }
  .fresh.stale { color: var(--warn); }
  .fresh.stale .dot { background: var(--warn); box-shadow: 0 0 0 3px rgba(245,181,74,0.18); }

  /* ---------- search ---------- */
  .search-box { position: relative; margin-bottom: 14px; }
  .search-box svg { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
  .search-box input {
    width: 100%; font: inherit; font-size: 1rem; background: var(--surface);
    border: 1px solid var(--line); border-radius: 12px; padding: 0 14px 0 40px; height: 48px; color: var(--ink);
  }
  .search-box input::placeholder { color: var(--muted); }
  .search-box input:focus { outline: none; border-color: var(--ball); box-shadow: 0 0 0 3px rgba(217,244,90,0.15); }
  .search-hint { color: var(--ink-soft); font-size: 0.9rem; line-height: 1.5; margin: 4px 2px 16px; }

  .player-suggestions { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
  .player-suggestion {
    display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; min-height: 52px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 12px;
    padding: 8px 12px; cursor: pointer; color: var(--ink);
  }
  .player-suggestion:hover, .player-suggestion:focus-visible { border-color: var(--ball); }
  .player-suggestion span { display: flex; flex-direction: column; font-size: 0.95rem; font-weight: 600; }
  .player-suggestion em { font-style: normal; font-size: 0.78rem; color: var(--muted); font-weight: 500; }

  /* ---------- "Mano" ---------- */
  .me { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 2px 0 12px; }
  .me .name { font-family: var(--display); font-weight: 700; font-size: 1.1rem; }
  .me .sub { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }
  .ghost-btn {
    flex: none; min-height: 44px; padding: 0 16px; border-radius: 999px; cursor: pointer;
    background: var(--surface); border: 1px solid var(--line); color: var(--ink-soft); font-size: 0.84rem; font-weight: 600;
  }
  .ghost-btn:hover { color: var(--ink); border-color: var(--ball); }

  .next {
    background: linear-gradient(160deg, #15444a, var(--surface)); border: 1px solid rgba(217,244,90,0.35);
    border-radius: 18px; padding: 16px; margin-bottom: 14px;
  }
  .next .label { font-family: var(--display); font-size: 0.7rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ball); font-weight: 700; }
  .next .big { font-family: var(--display); font-weight: 800; font-size: 3rem; line-height: 1.05; margin: 6px 0 2px; font-variant-numeric: tabular-nums; }
  .next .planned { font-size: 0.84rem; color: var(--muted); }
  .next .court-big { font-family: var(--display); font-weight: 700; font-size: 1.2rem; margin: 12px 0 4px; }
  .next .vs { font-size: 0.9rem; color: var(--ink-soft); line-height: 1.5; }
  .next .vs b { color: var(--ink); font-weight: 600; }
  .next .queue { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); font-size: 0.84rem; color: var(--ink-soft); line-height: 1.45; display: flex; gap: 8px; }
  .next .done { font-family: var(--display); font-size: 1.15rem; font-weight: 700; margin-top: 6px; }
  .notify-btn {
    margin-top: 14px; width: 100%; min-height: 48px; border-radius: 12px; cursor: pointer;
    border: 1px solid rgba(217,244,90,0.45); background: rgba(217,244,90,0.10); color: var(--ball);
    font-weight: 600; font-size: 0.92rem;
  }
  .notify-btn.on { background: var(--ball); color: var(--ball-ink); border-color: var(--ball); }
  .ni { width: 1.1em; height: 1.1em; flex-shrink: 0; vertical-align: -0.18em; }
  .notify-btn .ni { margin-right: 8px; }
  .notify-note .ni { margin-right: 4px; }
  .next .done .ni { color: var(--ball); margin-right: 8px; }
  /* A score that changed since the last refresh glows lime once. */
  @media (prefers-reduced-motion: no-preference) {
    .set-scores.flash .set-cell { animation: score-flash 1.6s cubic-bezier(.16,1,.3,1); }
    ::view-transition-old(root), ::view-transition-new(root) { animation-duration: 160ms; }
  }
  @media (prefers-reduced-motion: reduce) {
    .set-scores.flash { outline: 2px solid var(--ball); outline-offset: 3px; border-radius: 6px; }
  }
  @keyframes score-flash { 0%, 35% { color: var(--ball-ink); background: var(--ball); } 100% { background: transparent; } }
  .notify-note { font-size: 0.78rem; color: var(--muted); line-height: 1.45; margin-top: 8px; }

  /* ---------- iPhone: invite to the App Store app (push works there without home-screen tricks) ---------- */
  .app-ic {
    flex: none; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    background: rgba(217,244,90,0.12); color: var(--ball);
  }
  .app-ic svg { width: 20px; height: 20px; }
  .app-get {
    display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 50px; border-radius: 14px;
    background: var(--ball); color: var(--ball-ink); font-weight: 700; font-size: 1rem; text-decoration: none; border: 0; cursor: pointer;
  }
  .app-get svg { width: 18px; height: 18px; }
  .app-inline { margin-top: 14px; padding: 14px; border-radius: 14px; background: var(--surface-2); border: 1px solid rgba(217,244,90,0.3); }
  .app-inline .h { display: flex; align-items: center; gap: 10px; font-family: var(--display); font-weight: 700; font-size: 0.95rem; line-height: 1.25; }
  .app-inline p { font-size: 0.84rem; color: var(--ink-soft); line-height: 1.45; margin: 8px 0 12px; }
  .app-inline .alt { font-size: 0.76rem; color: var(--muted); text-align: center; margin: 10px 0 0; line-height: 1.4; }

  .app-dim { position: fixed; inset: 0; z-index: 40; background: rgba(3,14,16,0.55); }
  dialog.app-sheet {
    position: fixed; z-index: 41; inset: auto 8px calc(8px + env(safe-area-inset-bottom)) 8px; margin: 0 auto;
    width: auto; max-width: 560px; max-height: calc(100% - 24px); overflow-y: auto; overscroll-behavior: contain;
    padding: 18px 18px 14px; color: var(--ink); text-align: left;
    background: linear-gradient(170deg, #16464C, var(--surface) 60%);
    border: 1px solid rgba(217,244,90,0.35); border-radius: 24px; box-shadow: 0 -10px 40px rgba(0,0,0,0.4);
  }
  dialog.app-sheet[open] { animation: app-sheet-in 0.32s cubic-bezier(.2,.8,.2,1); }
  @keyframes app-sheet-in { from { transform: translateY(24px); opacity: 0; } }
  .app-sheet .head { display: flex; gap: 12px; align-items: center; padding-right: 36px; }
  .app-sheet .head img { width: 56px; height: 56px; border-radius: 13px; flex: none; }
  .app-sheet h2 { margin: 0; font-family: var(--display); font-weight: 800; font-size: 1.25rem; line-height: 1.15; text-wrap: balance; }
  .app-sheet .sub { font-size: 0.8rem; color: var(--ink-soft); margin-top: 3px; }
  .app-sheet .x {
    position: absolute; top: 12px; right: 12px; width: 36px; height: 36px; border-radius: 50%; border: 0; cursor: pointer;
    background: rgba(255,255,255,0.08); color: var(--ink-soft); display: flex; align-items: center; justify-content: center;
  }
  .app-sheet .x svg { width: 18px; height: 18px; }
  .app-sheet ul { list-style: none; padding: 0; margin: 16px 0; display: flex; flex-direction: column; gap: 12px; }
  .app-sheet li { display: flex; gap: 12px; align-items: flex-start; font-size: 0.86rem; line-height: 1.4; color: var(--ink-soft); }
  .app-sheet li b { display: block; color: var(--ink); font-size: 0.94rem; }
  .app-later {
    display: block; width: 100%; min-height: 44px; margin-top: 6px; background: none; border: 0; cursor: pointer;
    color: var(--muted); font-size: 0.88rem; font-weight: 600;
  }

  .section {
    font-family: var(--display); font-size: 0.72rem; letter-spacing: 0.12em; text-transform: uppercase;
    color: var(--muted); font-weight: 700; margin: 20px 2px 10px; display: flex; justify-content: space-between; gap: 8px;
  }
  .fold-btn {
    display: flex; justify-content: space-between; align-items: center; width: 100%; min-height: 50px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 0 14px;
    color: var(--ink-soft); font-size: 0.9rem; cursor: pointer; margin-bottom: 10px; text-align: left;
  }
  .fold-btn:hover { border-color: var(--ball); color: var(--ink); }

  /* ---------- match card ---------- */
  .time-head {
    font-family: var(--display); font-weight: 700; font-size: 1rem; margin: 22px 0 8px;
    display: flex; align-items: baseline; gap: 8px; scroll-margin-top: 14px;
  }
  .time-head .n { font-size: 0.72rem; font-weight: 600; color: var(--muted); }
  .match {
    background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
    padding: 12px 14px 12px; margin-bottom: 10px;
  }
  .match.mine { border-color: rgba(217,244,90,0.35); }
  .match-top { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }
  .when { display: flex; align-items: baseline; gap: 8px; }
  .when .t { font-family: var(--display); font-weight: 700; font-size: 1.15rem; font-variant-numeric: tabular-nums; }
  .when .p { font-size: 0.76rem; color: var(--muted); }
  .match-top .spacer { flex: 1; }
  .court-tag { font-family: var(--display); font-weight: 700; font-size: 0.86rem; color: var(--ink); }
  .badges { display: flex; gap: 6px; flex-wrap: wrap; margin: -2px 0 8px; }
  .badge {
    font-family: var(--display); font-size: 0.75rem; font-weight: 700; letter-spacing: 0.02em;
    padding: 3px 9px; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--line);
    color: var(--ink-soft); white-space: nowrap;
  }
  .badge.live { background: var(--ball); border-color: var(--ball); color: var(--ball-ink); animation: pulse 1.6s ease-in-out infinite; }
  .badge.division { color: var(--muted); }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.55; } }

  .match-row { display: flex; align-items: center; gap: 10px; padding: 4px 0; }
  .chip {
    width: 30px; height: 30px; border-radius: 10px; flex: none; display: flex; align-items: center;
    justify-content: center; font-family: var(--display); font-size: 0.72rem; font-weight: 800;
    color: rgba(10,34,38,0.82); position: relative; overflow: hidden;
  }
  .chip img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; background: #fff; }
  .row-text { min-width: 0; flex: 1; }
  .row-text b { display: block; font-size: 0.9rem; font-weight: 600; color: var(--ink-soft); line-height: 1.25; }
  .row-text span { display: block; font-size: 0.86rem; color: var(--muted); line-height: 1.35; overflow-wrap: anywhere; }
  .match-row.winner .row-text b { color: var(--ink); }
  .match-row.winner .row-text span { color: var(--ink-soft); }
  .row-text .me-name { display: inline; color: var(--ball); font-weight: 600; }
  .set-scores { display: flex; gap: 9px; flex: none; padding: 0 4px; }
  .set-cell {
    font-family: var(--display); font-weight: 700; font-size: 1rem; color: var(--ink-soft);
    font-variant-numeric: tabular-nums; min-width: 15px; text-align: center;
  }
  .set-cell.won { color: var(--ball); }
  .unsched { border: 1px dashed rgba(234,243,241,0.18); border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; }
  .unsched .match-top { margin-bottom: 4px; }

  /* ---------- "Dabar" ---------- */
  .court {
    background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; margin-bottom: 10px;
  }
  .court h3 { font-family: var(--display); font-size: 1.02rem; margin: 0 0 6px; }
  .crow { display: grid; grid-template-columns: 70px 1fr; gap: 10px; padding: 7px 0; align-items: start; }
  .crow + .crow { border-top: 1px solid var(--line); }
  .crow .k { font-family: var(--display); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); font-weight: 700; padding-top: 3px; }
  .crow .k.live { color: var(--ball); }
  .crow .v { font-size: 0.88rem; line-height: 1.45; min-width: 0; }
  .crow .v .tt { font-family: var(--display); font-weight: 700; font-size: 1.02rem; font-variant-numeric: tabular-nums; margin-right: 6px; }
  .crow .v .sub { color: var(--muted); }
  .crow .over { display: block; font-size: 0.78rem; color: var(--warn); margin-top: 2px; }
  .crow .pp { display: block; font-size: 0.8rem; color: var(--ink-soft); margin-top: 1px; }
  .pulse-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: var(--ball); margin-right: 6px; vertical-align: middle; animation: pulse 1.6s ease-in-out infinite; }
  .hint { font-size: 0.78rem; color: var(--muted); line-height: 1.5; margin: 8px 2px 0; }

  /* ---------- pills / club chips ---------- */
  .pills, .club-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
  .pill {
    font-family: var(--display); font-size: 0.8rem; font-weight: 600; color: var(--ink-soft);
    background: var(--surface); border: 1px solid var(--line); border-radius: 999px;
    min-height: 40px; padding: 0 15px; cursor: pointer;
  }
  .pill.active { color: var(--ball-ink); background: var(--ball); border-color: var(--ball); }
  .club-chip {
    display: flex; align-items: center; gap: 8px; font-family: var(--display); font-size: 0.84rem;
    font-weight: 600; background: var(--surface); border: 1px solid var(--line); border-radius: 999px;
    min-height: 44px; padding: 6px 15px 6px 7px; cursor: pointer; color: var(--ink-soft);
  }
  .club-chip.active { color: var(--ink); background: var(--surface-2); border-color: var(--chip-c, var(--ink-soft)); }

  /* ---------- standings ---------- */
  .division-block { margin-bottom: 26px; }
  .legend { font-size: 0.8rem; color: var(--muted); line-height: 1.5; margin: -4px 0 22px; }
  .division-title { font-family: var(--display); font-size: 1rem; font-weight: 700; margin: 0 0 10px; }
  .standings-list { display: flex; flex-direction: column; gap: 6px; }
  .standing-row {
    display: flex; align-items: center; gap: 10px; background: var(--surface);
    border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px;
  }
  .standing-rank { font-family: var(--display); font-weight: 800; font-size: 0.92rem; color: var(--muted); width: 18px; text-align: center; flex: none; }
  .standing-rank.top { color: var(--ball); }
  .standing-club { flex: 1; min-width: 0; font-weight: 600; font-size: 0.92rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .standing-stats { display: flex; gap: 10px; font-family: var(--display); font-size: 0.8rem; color: var(--ink-soft); font-variant-numeric: tabular-nums; flex: none; }
  .standing-stats em { font-style: normal; color: var(--muted); font-size: 0.75rem; display: block; text-align: center; }
  .standing-diff { min-width: 34px; text-align: right; }
  .standing-diff.pos { color: var(--ball); }
  /* club-vs-club meetings (club league "Grupės") */
  .meetings { display: flex; flex-direction: column; gap: 6px; }
  .meeting { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; }
  .meeting summary { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); align-items: center; gap: 4px 10px; padding: 10px 12px; cursor: pointer; list-style: none; }
  .meeting summary::-webkit-details-marker { display: none; }
  .meeting-club { display: flex; align-items: center; gap: 8px; min-width: 0; font-weight: 600; font-size: 0.88rem; }
  .meeting-club > span:not(.chip) { min-width: 0; line-height: 1.2; overflow-wrap: anywhere; color: var(--ink-soft); }
  .meeting-club .win { color: var(--ink); }
  .meeting-club.right { justify-content: flex-end; text-align: right; }
  .meeting-score { font-family: var(--display); font-weight: 800; font-size: 1.15rem; font-variant-numeric: tabular-nums; }
  .meeting-status { grid-column: 1 / -1; text-align: center; font-size: 0.75rem; color: var(--muted); }
  .meeting[open] .meeting-status { color: var(--ink-soft); }
  .meeting-status svg { vertical-align: -2px; transition: transform .2s; }
  .meeting[open] .meeting-status svg { transform: rotate(180deg); }
  @media (prefers-reduced-motion: reduce) { .meeting-status svg { transition: none; } }
  .meeting-matches { list-style: none; margin: 0; padding: 0 12px 10px; display: flex; flex-direction: column; gap: 6px; border-top: 1px solid var(--line); padding-top: 8px; }
  .meeting-matches li { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 2px 8px; font-size: 0.8rem; color: var(--muted); }
  .meeting-matches .mm-div { grid-column: 1 / -1; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
  .meeting-matches li > span:nth-child(4) { text-align: right; }
  .meeting-matches .win { color: var(--ink); font-weight: 600; }
  .mm-sets { font-family: var(--display); font-variant-numeric: tabular-nums; color: var(--ink-soft); white-space: nowrap; }
  /* club table carries four figures: on a phone they move under the club name */
  @media (max-width: 480px) {
    .standing-row.club-row { flex-wrap: wrap; row-gap: 6px; }
    .standing-row.club-row .standing-stats { flex-basis: 100%; justify-content: space-between; padding-left: 28px; }
  }

  /* ---------- groups & brackets ---------- */
  .cat-chips { display: flex; gap: 6px; overflow-x: auto; margin: 0 -14px 10px; padding: 2px 14px 6px; scrollbar-width: none; }
  .cat-chips::-webkit-scrollbar { display: none; }
  .cat-chip {
    flex: none; font-family: var(--display); font-size: 0.8rem; font-weight: 600; color: var(--ink-soft);
    background: var(--surface); border: 1px solid var(--line); border-radius: 999px; min-height: 38px; padding: 0 14px; cursor: pointer;
  }
  .cat-chip.active { color: var(--ball-ink); background: var(--ball); border-color: var(--ball); }
  .seg { display: flex; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 3px; margin: 0 0 12px; overflow-x: auto; }
  .seg button { flex: 1 0 auto; font-size: 0.8rem; padding: 8px 12px; border-radius: 9px; color: var(--ink-soft); background: none; border: 0; cursor: pointer; white-space: nowrap; }
  .seg button.active { background: var(--surface-2); color: var(--ink); font-weight: 600; }
  .champ { margin: 0 0 12px; background: linear-gradient(160deg, #15444a, var(--surface)); border: 1px solid rgba(217,244,90,.35);
    border-radius: 16px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; }
  .champ .cup { display: inline-flex; color: var(--ball); flex-shrink: 0; }
  .champ .cup svg { width: 26px; height: 26px; }
  .champ .l { font-size: 0.75rem; letter-spacing: .1em; text-transform: uppercase; color: var(--ball); font-weight: 700; }
  .champ .n { font-family: var(--display); font-weight: 700; font-size: 1rem; }
  .bracket-scroll { overflow-x: auto; margin: 0 -14px; padding: 0 14px 8px; scrollbar-width: thin; scroll-behavior: smooth; }
  .bracket { display: flex; gap: 18px; width: max-content; }
  .bcol { width: 190px; flex: none; }
  .bhead { font-size: 0.75rem; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); font-weight: 700; height: 22px; white-space: nowrap; }
  .pairbox { position: relative; }
  .pairbox.joined::after { content: ""; position: absolute; right: -10px; top: 25%; bottom: 25%; width: 10px;
    border: 2px solid rgba(234,243,241,0.16); border-left: 0; border-radius: 0 6px 6px 0; }
  .slot { display: flex; align-items: center; }
  .bm { position: relative; width: 100%; background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 5px 8px; }
  .bcol:not(.first) .bm::before { content: ""; position: absolute; left: -10px; top: 50%; width: 9px; border-top: 2px solid rgba(234,243,241,0.16); }
  .bm.mine { border-color: rgba(217,244,90,.55); box-shadow: 0 0 0 1px rgba(217,244,90,.15) inset; }
  .bm.live { border-color: var(--ball); }
  .bmeta { font-size: 0.75rem; color: var(--muted); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .bs { display: flex; justify-content: space-between; align-items: center; gap: 6px; font-size: 0.76rem; line-height: 1.5; color: var(--ink-soft); }
  .bs .bn { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .bs.won { color: var(--ink); font-weight: 700; }
  .bs.lost { color: var(--muted); }
  .bs.isme .bn { color: var(--ball); }
  .bsets { display: flex; gap: 5px; font-family: var(--display); font-variant-numeric: tabular-nums; font-weight: 600; flex: none; }
  .bsets .w { color: var(--ink); font-weight: 800; }
  .bs.lost .bsets span { color: var(--muted); }
  .bye { color: var(--muted); font-style: italic; font-weight: 400; }
  .placement { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; margin-top: 12px; }
  .placement .bhead { height: auto; margin-bottom: 4px; }
  .gtable { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
  .gtable-wrap { overflow-x: auto; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; }
  .gtable th { font-size: 0.75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); font-weight: 700; text-align: center; padding: 8px 6px; }
  .gtable th:nth-child(2) { text-align: left; }
  .gtable td { padding: 8px 6px; border-top: 1px solid var(--line); text-align: center; font-variant-numeric: tabular-nums; font-family: var(--display); }
  .gtable td.pn { text-align: left; font-family: inherit; min-width: 140px; }
  .gtable td.pos { color: var(--muted); font-weight: 800; width: 24px; }
  .gtable tr.top td.pos { color: var(--ball); }
  .gtable tr.isme td.pn { color: var(--ball); font-weight: 600; }
  .gtable td.pts { font-weight: 800; color: var(--ink); }
  .section-sub { font-family: var(--display); font-size: 0.95rem; font-weight: 700; margin: 18px 0 10px; }
  .empty { color: var(--muted); font-size: 0.92rem; padding: 32px 0; text-align: center; line-height: 1.5; }
  footer { border-top: 1px solid var(--line); margin-top: 40px; padding: 16px 0 20px; font-size: 0.76rem; color: var(--muted); display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  footer a { color: var(--ink-soft); }

  [data-panel] { display: none; }
  [data-panel].active { display: block; }

  /* ---------- bottom nav ---------- */
  nav.bottom-nav {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 10;
    background: rgba(19,58,62,0.98); backdrop-filter: blur(14px);
    border-top: 1px solid rgba(217,244,90,0.16); box-shadow: 0 -12px 28px rgba(3,12,14,0.5);
    display: flex; gap: 4px; padding: 6px 6px calc(8px + env(safe-area-inset-bottom));
  }
  .nav-btn {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
    background: none; border: none; color: var(--ink-soft); font-size: 0.75rem; font-weight: 600;
    min-height: 52px; padding: 4px 2px; cursor: pointer; border-radius: 12px;
  }
  .nav-btn svg { width: 22px; height: 22px; }
  .nav-btn.active { color: var(--ball); background: rgba(217,244,90,0.12); }

  @media (min-width: 640px) {
    .wrap { padding: 0 24px; }
    nav.bottom-nav { position: static; background: none; backdrop-filter: none; border: none; box-shadow: none; padding: 0; margin-bottom: 18px; justify-content: flex-start; }
    .nav-btn { flex: none; flex-direction: row; gap: 7px; min-height: 42px; padding: 0 14px; font-size: 0.84rem; border: 1px solid transparent; }
    .nav-btn svg { width: 18px; height: 18px; }
    .nav-btn.active { background: var(--surface); border-color: var(--line); }
    body { padding-bottom: 0; }
  }

  button:focus-visible, input:focus-visible { outline: 2px solid var(--ball); outline-offset: 2px; }
  @media (prefers-reduced-motion: reduce) {
    .badge.live, .pulse-dot { animation: none; }
    html { scroll-behavior: auto; }
  }
</style>
</head>
<body>
<noscript>
  <section class="noscript-summary" style="padding:16px">
    <h2>{{ $tName }} – mačai ir rezultatai</h2>
    <p>{{ $seoDesc }}</p>
    @if($divisionList->isNotEmpty())<p>Kategorijos: {{ $divisionList->implode(', ') }}.</p>@endif
    @if($hasClubs)
    <ul>
      @foreach($matches as $m)
        @php
          $score = collect($m['sets'] ?? [])->map(fn ($x) => ($x['side1'] ?? 0) . '-' . ($x['side2'] ?? 0))->implode(' ');
        @endphp
        @if(!empty($m['team1']['title']) && !empty($m['team2']['title']))
        <li>{{ trim(($m['date'] ?? '') . ' ' . ($m['time'] ?? '')) }} {{ $m['court']['name'] ?? '' }} · {{ $m['division'] ?? '' }} · {{ $m['team1']['title'] }} – {{ $m['team2']['title'] }}{{ $score ? ' · ' . $score : '' }}</li>
        @endif
      @endforeach
    </ul>
    @endif
  </section>
</noscript>
<div class="wrap">

  <header class="top">
    <h1 class="brand">{{ $tName }}<small id="tour-date"></small></h1>
    <div class="fresh" id="fresh" role="status"><span class="dot"></span><span id="fresh-text"></span></div>
  </header>

  <nav class="bottom-nav" aria-label="Skiltys">
    <button class="nav-btn active" data-tab="mano" aria-current="page">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></svg>
      Mano
    </button>
    <button class="nav-btn" data-tab="dabar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
      Dabar
    </button>
    <button class="nav-btn" data-tab="maciai">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      Mačai
    </button>
    @if($hasClubs)
    <button class="nav-btn" data-tab="klubai">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Klubai
    </button>
    @endif
    @if($hasGroupsTab)
    <button class="nav-btn" data-tab="grupes">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/></svg>
      Grupės
    </button>
    @endif
    @if($hasDraws)
    <button class="nav-btn" data-tab="lenteles">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4a2 2 0 0 0 2 4M17 6h3a2 2 0 0 1-2 4"/></svg>
      Lentelės
    </button>
    @endif
  </nav>

  {{-- Mano --}}
  <div data-panel="mano" class="active" id="mano"></div>

  {{-- Dabar --}}
  <div data-panel="dabar" id="dabar"></div>

  {{-- Mačai --}}
  <div data-panel="maciai">
    <div class="search-box">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input type="search" id="list-filter" placeholder="Filtruok pagal žaidėją ar klubą…" autocomplete="off" aria-label="Filtruoti mačus">
    </div>
    @if($divisionList->isNotEmpty())
    <div class="pills" id="division-pills">
      <button class="pill active" data-division="">Visi lygiai</button>
      @foreach($divisionList as $d)
        <button class="pill" data-division="{{ $d }}">{{ $d }}</button>
      @endforeach
    </div>
    @endif
    <div id="grid-results"></div>
  </div>

  @if($hasClubs)
  {{-- Klubai --}}
  <div data-panel="klubai">
    <div class="club-chips" id="club-chips"></div>
    <div id="club-results"></div>
  </div>
  @endif

  {{-- Grupės: club table and/or player group tables --}}
  <div data-panel="grupes">
    <div id="standings-results"></div>
    <div id="groups-results"></div>
  </div>

  {{-- Lentelės: knockout brackets --}}
  <div data-panel="lenteles">
    <div id="brackets-results"></div>
  </div>

  <footer>
    <span>Grafikas gyvai iš Tournated · {{ $tName }}</span>
    <a href="{{ route('home') }}">padelioturnyrai.lt</a>
  </footer>
</div>

{{-- iPhone in Safari: once, a few seconds in, explain why the App Store app is handier.
     Not shown in the home-screen web app, nor when the app itself opened this page (?app=1). --}}
<div class="app-dim" id="app-dim" hidden></div>
<dialog class="app-sheet" id="app-sheet" aria-labelledby="app-sheet-title">
  <button type="button" class="x" data-app-close aria-label="Uždaryti">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
  </button>
  <div class="head">
    <img src="{{ route('push.icon', 192) }}" alt="" width="56" height="56">
    <div><h2 id="app-sheet-title">Turnyras patogiau programėlėje</h2><div class="sub">Padelio turnyrai · nemokama</div></div>
  </div>
  <ul>
    <li><span class="app-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span>
      <span><b>Pranešimas, kai tavo eilė</b>Telefonas pasakys, į kurį kortą eiti – nereikia sekti grafiko.</span></li>
    <li><span class="app-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M6 21v-1a6 6 0 0 1 12 0v1"/></svg></span>
      <span><b>Tavo mačai iškart</b>Pasirink save vieną kartą – matysi tik savo mačus ir laiką.</span></li>
    <li><span class="app-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg></span>
      <span><b>Visi turnyrai vienoje vietoje</b>Rezultatai tiesiogiai ir artėjantys turnyrai – be nuorodų paieškos.</span></li>
  </ul>
  <a class="app-get" data-app-get href="https://apps.apple.com/lt/app/padelio-turnyrai/id6833871129" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>Atsisiųsti iš App Store</a>
  <button type="button" class="app-later" data-app-close>Ne dabar, žiūrėsiu naršyklėje</button>
</dialog>

<script>
window.AppPromo = (function () {
  var URL = 'https://apps.apple.com/lt/app/padelio-turnyrai/id6833871129';
  var KEY = 'appPromo:until';
  var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  // the app opens this page with ?app=1 – remember it for the rest of that browser session
  var fromApp = /[?&]app=1(&|$)/.test(location.search);
  try { if (fromApp) sessionStorage.setItem('fromApp', '1'); else fromApp = sessionStorage.getItem('fromApp') === '1'; } catch (e) {}
  var active = isIOS && !standalone && !fromApp;

  function snooze(days) { try { localStorage.setItem(KEY, String(Date.now() + days * 864e5)); } catch (e) {} }
  function snoozed() { try { return Date.now() < Number(localStorage.getItem(KEY) || 0); } catch (e) { return false; } }

  var sheet = document.getElementById('app-sheet'), dim = document.getElementById('app-dim');
  function close(days) {
    if (sheet.open) sheet.close();
    dim.hidden = true;
    snooze(days);
  }
  if (active && !snoozed() && typeof sheet.show === 'function') {
    setTimeout(function () {
      if (snoozed()) return;
      dim.hidden = false;
      sheet.show();
    }, 4000);
  }
  dim.addEventListener('click', function () { close(14); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sheet.open) close(14); });
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-app-close]')) close(14);
    // tapped "download": don't nag again for two months (the Safari bar switches to "Atidaryti")
    else if (e.target.closest('[data-app-get]')) close(60);
  });

  return {
    active: active,
    // the "Mano" card: shown where the "notify me" button would be
    inline: function () {
      return '<div class="app-inline"><div class="h"><span class="app-ic">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>' +
        '</span>Gauk pranešimą, kai tavo eilė</div>' +
        '<p>Programėlėje „Padelio turnyrai“ telefonas pats praneš, kada ir į kurį kortą eiti. Nemokama, užtrunka minutę.</p>' +
        '<a class="app-get" data-app-get href="' + URL + '" target="_blank" rel="noopener">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>Atsisiųsti iš App Store</a>' +
        '<p class="alt">Arba be programėlės: „Safari“ spausk Bendrinti → „Į pradžios ekraną“ ir atsidaryk grafiką iš ten.</p></div>';
    }
  };
})();
</script>

<script>
// Phone notifications ("Tavo eilė! Eik į Kortą 4"): one subscription per
// phone, tied to the player chosen on the "Mano" tab.
window.PushUI = (function () {
  var VAPID = @json($pushEnabled ? config('services.push.vapid_public') : null);
  var SUBSCRIBE_URL = @json(route('push.subscribe', $tournamentId));
  var UNSUBSCRIBE_URL = @json(route('push.unsubscribe'));
  var KEY = 'grafikas:' + @json($tournamentId) + ':push';
  var busy = false, slot = null, player = null;

  function get() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function set(v) { try { v ? localStorage.setItem(KEY, v) : localStorage.removeItem(KEY); } catch (e) {} }
  function b64ToBytes(b64) {
    var s = (b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(s), out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { if (!r.ok) throw new Error(r.status); });
  }
  var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;

  function render(el, who) {
    slot = el || slot; player = who || player;
    if (!slot) return;
    // iPhone in Safari: the App Store app is the easy way to get "your turn" alerts
    if (isIOS && !standalone && window.AppPromo && window.AppPromo.active) { slot.innerHTML = window.AppPromo.inline(); return; }
    if (!VAPID) return;
    if (!supported) {
      slot.innerHTML = '';
      return;
    }
    if (Notification.permission === 'denied') {
      slot.innerHTML = '<p class="notify-note"><svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.7 3A6 6 0 0 1 18 8a21.3 21.3 0 0 0 .6 5"/><path d="M17 17H3s3-2 3-9a4.67 4.67 0 0 1 .3-1.7"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="m2 2 20 20"/></svg> Pranešimai šiai svetainei užblokuoti telefono nustatymuose.</p>';
      return;
    }
    var on = get() === player && Notification.permission === 'granted';
    slot.innerHTML = '<button class="notify-btn' + (on ? ' on' : '') + '" id="notify-btn"' + (busy ? ' disabled' : '') + '>' +
      (busy ? 'Palauk…' : (on ? '<svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>Pranešimai įjungti · išjungti' : '<svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>Pranešti man, kai mano eilė')) + '</button>' +
      '<p class="notify-note">' + (on
        ? 'Gausi žinutę, kai bus tavo eilė eiti į kortą, ir jei pasikeis kortas, laikas ar poros.'
        : 'Žinutė ateis, kai prieš tave esantis mačas baigsis – tada laikas eiti į kortą.') + '</p>';
    document.getElementById('notify-btn').onclick = on ? disable : enable;
  }
  function enable() {
    busy = true; render();
    navigator.serviceWorker.register('/sw.js').then(function (reg) {
      return Notification.requestPermission().then(function (perm) {
        if (perm !== 'granted') throw new Error('denied');
        return navigator.serviceWorker.ready;
      }).then(function (ready) {
        return ready.pushManager.getSubscription().then(function (sub) {
          return sub || ready.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(VAPID) });
        });
      });
    }).then(function (sub) {
      return post(SUBSCRIBE_URL, { player: player, subscription: sub.toJSON() });
    }).then(function () { set(player); })
      .catch(function () {})
      .then(function () { busy = false; render(); });
  }
  function disable() {
    busy = true; render();
    navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
      if (!sub) return;
      return post(UNSUBSCRIBE_URL, { endpoint: sub.endpoint }).then(function () { return sub.unsubscribe(); });
    }).catch(function () {}).then(function () { set(null); busy = false; render(); });
  }
  return { render: render };
})();

(function () {
  var TOURNAMENT_ID = @json($tournamentId);
  var DATA_URL = @json(route('schedule.data', $tournamentId));
  var TOURNAMENT_DATE = @json($tDate);
  var CLUB_LIST = @json($clubList->values());
  var HAS_CLUBS = @json($hasClubs);
  var HAS_GROUP_TABLES = @json($hasGroupTables);
  var TZ = 'Europe/Vilnius';
  var STORE_KEY = 'grafikas:' + TOURNAMENT_ID + ':player';
  var state = {
    matches: @json(array_values($matches)),
    groups: @json(array_values($groups)),
    draws: @json(array_values($draws)),
    // club-vs-club rosters: who plays for which club before pairs are published
    ties: @json(array_values($ties ?? [])),
    // league points per stage (12/9/7/4), published by Tournated once the stage ends
    leaguePoints: @json(array_values($leaguePoints ?? [])),
    syncedAt: @json($syncedAt),
    lastFetchOk: true
  };

  /* ---------- small helpers ---------- */
  function esc(s) { return (s ?? '').toString().replace(/[&<>"]/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }
  function fold(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function store(get, val) {
    try { if (get) return localStorage.getItem(STORE_KEY); if (val) localStorage.setItem(STORE_KEY, val); else localStorage.removeItem(STORE_KEY); }
    catch (e) { return null; }
  }
  // Lithuanian plural: 1 mačas, 2 mačai, 10 mačų, 21 mačas
  function plural(n, one, few, many) {
    var d = n % 10, h = n % 100;
    if (d === 1 && h !== 11) return n + ' ' + one;
    if (d >= 2 && d <= 9 && (h < 11 || h > 19)) return n + ' ' + few;
    return n + ' ' + many;
  }
  function toMin(t) { var p = (t || '').split(':'); return (+p[0]) * 60 + (+p[1]); }
  function hhmm(min) { min = Math.max(0, Math.min(min, 1439)); return String(Math.floor(min / 60)).padStart(2, '0') + ':' + String(min % 60).padStart(2, '0'); }
  // Tournament clock: always Vilnius time, whatever the phone's timezone.
  function nowLocal() {
    var parts = {};
    new Intl.DateTimeFormat('en-GB', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false })
      .formatToParts(new Date()).forEach(function (p) { parts[p.type] = p.value; });
    return { date: parts.year + '-' + parts.month + '-' + parts.day, min: (+parts.hour % 24) * 60 + (+parts.minute) };
  }
  function localTimeOf(iso) {
    if (!iso) return null;
    try { return new Date(iso).toLocaleTimeString('lt-LT', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TZ }); } catch (e) { return null; }
  }
  function dayLabel(date) {
    try {
      var s = new Date(date + 'T12:00:00Z').toLocaleDateString('lt-LT', { weekday: 'long', month: 'long', day: 'numeric', timeZone: 'UTC' });
      return s.charAt(0).toUpperCase() + s.slice(1);
    } catch (e) { return date; }
  }
  // "Court 4" -> "Kortas 4"
  function courtLabel(m) {
    var name = (m && m.court && m.court.name) || '';
    var n = name.match(/\d+/);
    return n ? 'Kortas ' + n[0] : (name || 'Kortas nepaskirtas');
  }
  function courtNumber(m) { var n = ((m.court && m.court.name) || '').match(/\d+/); return n ? +n[0] : 999; }

  var CLUB_COLORS = ['#E9805C', '#E06FA3', '#A578E0', '#E0A34A', '#5FC98A', '#6FA8E0'];
  var clubColorCache = {};
  function hashStr(s) { var h = 0; for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0; return Math.abs(h); }
  function clubColor(name) {
    if (!name) return '#5A7A76';
    if (!clubColorCache[name]) clubColorCache[name] = CLUB_COLORS[hashStr(name) % CLUB_COLORS.length];
    return clubColorCache[name];
  }
  function initials(name) {
    if (!name) return '?';
    var words = name.trim().split(/\s+/).filter(Boolean);
    return words.length >= 2 ? (words[0][0] + words[1][0]).toUpperCase() : name.slice(0, 2).toUpperCase();
  }
  // Tournated sends a real club logo (team.image) on most matches — prefer
  // it, falling back to the colour-coded initials badge.
  var clubImages = {};
  function indexClubImages(matches) {
    matches.forEach(function (m) {
      [m.team1, m.team2].forEach(function (t) { if (t && t.title && t.image && !clubImages[t.title]) clubImages[t.title] = t.image; });
    });
  }
  function clubBadge(title, size) {
    size = size || 30;
    var html = '<span class="chip" style="background:' + clubColor(title) + ';width:' + size + 'px;height:' + size + 'px;font-size:' + Math.round(size * 0.4) + 'px">' + esc(initials(title));
    if (clubImages[title]) html += '<img src="' + esc(clubImages[title]) + '" alt="" loading="lazy" onerror="this.remove()">';
    return html + '</span>';
  }

  /* ---------- match model ---------- */
  function divisionGroup(name) { return (name || '').replace(/\s+\d+$/, ''); }
  function pairNames(participants, side) {
    return (participants || []).filter(function (p) { return p.side === side; })
      .map(function (p) { return ((p.name || '') + ' ' + (p.surname || '')).trim(); }).filter(Boolean);
  }
  function shortName(full) { var w = full.split(/\s+/); return w.length > 1 ? w[0][0] + '. ' + w.slice(1).join(' ') : full; }
  function isPlayed(m) { return !!((m.sets && m.sets.length) || m.winner_side || m.is_walkover || m.is_bye); }
  function isScheduled(m) { return !!(m.time && m.court && m.court.name); }
  // Expected start: the server's estimate for not-yet-played matches.
  function startMin(m) { return toMin(!isPlayed(m) && m.eta ? m.eta : m.time); }
  function sideOf(m, player) {
    var k = fold(player);
    if (pairNames(m.participants, 1).some(function (n) { return fold(n) === k; })) return 1;
    if (pairNames(m.participants, 2).some(function (n) { return fold(n) === k; })) return 2;
    return 0;
  }
  function sideTitle(m, side) {
    var team = side === 1 ? m.team1 : m.team2;
    if (team && team.title) return team.title;
    var names = pairNames(m.participants, side);
    return names.length ? names.map(shortName).join(' / ') : 'Nepaskelbta';
  }
  function byStart(a, b) {
    return (a.date || '').localeCompare(b.date || '') || (toMin(a.time) - toMin(b.time)) || (courtNumber(a) - courtNumber(b));
  }

  function setNumbers(sets, side) {
    return (sets || []).map(function (s) {
      var a = s.side1 ?? 0, b = s.side2 ?? 0;
      var mine = side === 1 ? a : b, theirs = side === 1 ? b : a;
      return '<span class="set-cell' + (mine > theirs ? ' won' : '') + '">' + mine + '</span>';
    }).join('');
  }

  // Time block: played -> the time it was played; estimated -> "~13:37" with
  // the planned time small next to it.
  function whenHtml(m) {
    if (!m.time) return '<div class="when"><span class="t">—</span></div>';
    if (!isPlayed(m) && m.eta && m.eta !== m.time) {
      return '<div class="when"><span class="t">~' + esc(m.eta) + '</span><span class="p">planuota ' + esc(m.time) + '</span></div>';
    }
    return '<div class="when"><span class="t">' + esc(m.time) + '</span></div>';
  }

  function matchRow(m, side, me) {
    var team = side === 1 ? m.team1 : m.team2;
    var names = pairNames(m.participants, side);
    var namesHtml = names.length ? names.map(function (n) {
      return me && fold(n) === fold(me) ? '<span class="me-name">' + esc(n) + '</span>' : esc(n);
    }).join(', ') : 'sudėtis nepaskelbta';
    var played = isPlayed(m), won = played && m.winner_side === side;
    var hasSets = played && m.sets && m.sets.length && !m.is_walkover;
    var head = HAS_CLUBS && team && team.title
      ? clubBadge(team.title, 30) + '<div class="row-text"><b>' + esc(team.title) + '</b><span>' + namesHtml + '</span></div>'
      : '<div class="row-text"><b>' + namesHtml + '</b></div>';
    return '<div class="match-row' + (won ? ' winner' : '') + '">' + head +
      (hasSets ? '<div class="set-scores">' + setNumbers(m.sets, side) + '</div>' : '') + '</div>';
  }

  function matchCard(m, me) {
    var badges = '';
    if (m.is_match_in_progress && !isPlayed(m) && m.date === nowLocal().date) badges += '<span class="badge live">● Vyksta</span>';
    if (m.division) badges += '<span class="badge division">' + esc(m.division) + '</span>';
    if (m.is_walkover) badges += '<span class="badge">Be žaidimo (W.O.)</span>';
    var cls = 'match' + (me && sideOf(m, me) ? ' mine' : '') + (isScheduled(m) ? '' : ' unsched');
    return '<div class="' + cls + '" data-m="' + esc(String(m.match_id || '')) + '">' +
      '<div class="match-top">' + whenHtml(m) + '<span class="spacer"></span><span class="court-tag">' + esc(isScheduled(m) ? courtLabel(m) : 'Laikas nepaskirtas') + '</span></div>' +
      (badges ? '<div class="badges">' + badges + '</div>' : '') +
      matchRow(m, 1, me) + matchRow(m, 2, me) +
    '</div>';
  }

  // Grouped by day (only shown when the tournament spans several) and planned
  // time; matches without a time go to their own section, never disappear.
  function gridHtml(matches, me) {
    if (!matches.length) return '';
    var scheduled = matches.filter(isScheduled).sort(byStart);
    var unscheduled = matches.filter(function (m) { return !isScheduled(m); });
    var multiDay = new Set(scheduled.map(function (m) { return m.date; })).size > 1;
    var html = '', groups = [], idx = {};
    scheduled.forEach(function (m) {
      var key = (m.date || '') + ' ' + m.time;
      if (!(key in idx)) { idx[key] = groups.length; groups.push({ date: m.date, time: m.time, list: [] }); }
      groups[idx[key]].list.push(m);
    });
    groups.forEach(function (g) {
      html += '<div class="time-head">' + (multiDay ? esc(dayLabel(g.date)) + ', ' : '') + esc(g.time) +
        ' <span class="n">· ' + plural(g.list.length, 'mačas', 'mačai', 'mačų') + '</span></div>';
      g.list.forEach(function (m) { html += matchCard(m, me); });
    });
    if (unscheduled.length) {
      html += '<div class="section"><span>Laikas dar nepaskirtas</span><span>' + unscheduled.length + '</span></div>';
      unscheduled.forEach(function (m) { html += matchCard(m, me); });
    }
    return html;
  }

  /* ---------- player index & search ---------- */
  function buildPlayerIndex() {
    var map = {};
    state.matches.forEach(function (m) {
      [1, 2].forEach(function (side) {
        var team = side === 1 ? m.team1 : m.team2;
        pairNames(m.participants, side).forEach(function (name) {
          var key = fold(name);
          if (!map[key]) map[key] = { name: name, club: (team && team.title) || '', division: divisionGroup(m.division) };
        });
      });
    });
    // Players whose club hasn't published its pairs yet are only on the roster.
    (state.ties || []).forEach(function (t) {
      [1, 2].forEach(function (side) {
        var team = side === 1 ? t.team1 : t.team2;
        pairNames(t.players, side).forEach(function (name) {
          var key = fold(name);
          if (!map[key]) map[key] = { name: name, club: (team && team.title) || '', division: '', rosterOnly: true };
        });
      });
    });
    return Object.keys(map).map(function (k) { return map[k]; }).sort(function (a, b) { return a.name.localeCompare(b.name, 'lt'); });
  }
  function matcher(query) {
    var words = fold(query).split(/\s+/).filter(Boolean);
    return function (text) { var t = fold(text); return words.every(function (w) { return t.indexOf(w) !== -1; }); };
  }
  function playerMatches(player) { return state.matches.filter(function (m) { return sideOf(m, player) > 0; }); }

  /* ---------- "Mano" ---------- */
  var me = null;
  try { me = new URLSearchParams(location.search).get('z') || store(true); } catch (e) { me = null; }

  function choosePlayer(name) {
    me = name; store(false, name);
    try { var u = new URL(location.href); u.searchParams.set('z', name); history.replaceState(null, '', u); } catch (e) {}
    renderMano(); renderGrid(); renderKlubai(); renderGroupTables(); renderBrackets();
    window.scrollTo(0, 0);
  }
  function forgetPlayer() {
    me = null; store(false, null);
    try { var u = new URL(location.href); u.searchParams.delete('z'); history.replaceState(null, '', u); } catch (e) {}
    renderMano(); renderGrid(); renderKlubai(); renderGroupTables(); renderBrackets();
  }

  var manoQuery = '';
  function renderManoSearch(box) {
    var html = '<div class="search-box">' +
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>' +
      '<input type="search" id="me-search" placeholder="Tavo vardas arba pavardė…" autocomplete="off" aria-label="Rask save" value="' + esc(manoQuery) + '">' +
      '</div>';
    if (!manoQuery) {
      html += '<p class="search-hint">Susirask save – telefonas tave prisimins ir čia visada rodys tavo kitą mačą: kada, kuriame korte ir prieš ką. Galima rašyti ir be lietuviškų raidžių.</p>';
    } else {
      var hit = matcher(manoQuery);
      var players = buildPlayerIndex().filter(function (p) { return hit(p.name); }).slice(0, 10);
      html += players.length ? '<div class="player-suggestions">' + players.map(function (p) {
        return '<button class="player-suggestion" data-player="' + esc(p.name) + '">' + (p.club ? clubBadge(p.club, 28) : '') +
          '<span>' + esc(p.name) + ((p.club || p.division) ? '<em>' + esc([p.club, p.division].filter(Boolean).join(' · ')) + '</em>' : '') + '</span></button>';
      }).join('') + '</div>' : '<p class="empty">Tokio žaidėjo nerasta.</p>';
    }
    box.innerHTML = html;
    var input = document.getElementById('me-search');
    input.addEventListener('input', function () {
      manoQuery = input.value; var pos = input.selectionStart;
      renderManoSearch(box);
      var again = document.getElementById('me-search'); again.focus(); try { again.setSelectionRange(pos, pos); } catch (e) {}
    });
  }

  // What is happening on this match's court before it starts.
  function queueText(m) {
    var court = courtLabel(m);
    var before = state.matches.filter(function (x) {
      return x !== m && !isPlayed(x) && x.date === m.date && x.court && m.court && x.court.name === m.court.name && toMin(x.time) < toMin(m.time);
    }).sort(byStart);
    var now = nowLocal();
    if (!before.length) return 'Tu – kitas korte ' + esc(court.replace('Kortas ', '')) + '.';
    if (before.length === 1 && m.date === now.date && startMin(before[0]) <= now.min) {
      return 'Korte ' + esc(court.replace('Kortas ', '')) + ' dabar žaidžia ' + esc(sideTitle(before[0], 1)) + ' – ' + esc(sideTitle(before[0], 2)) +
        ' (nuo ' + esc(before[0].eta || before[0].time) + '), tu – po jų.';
    }
    return 'Prieš tave korte ' + esc(court.replace('Kortas ', '')) + ' dar ' + plural(before.length, 'mačas', 'mačai', 'mačų') + '.';
  }

  var showPlayedMine = false;
  function renderMano() {
    var box = document.getElementById('mano');
    if (!me || !buildPlayerIndex().some(function (p) { return fold(p.name) === fold(me); })) {
      if (me) { me = null; store(false, null); }
      renderManoSearch(box);
      return;
    }
    var mine = playerMatches(me).sort(byStart);
    var upcoming = mine.filter(function (m) { return !isPlayed(m); });
    var played = mine.filter(isPlayed);
    var info = buildPlayerIndex().filter(function (p) { return fold(p.name) === fold(me); })[0] || {};

    var html = '<div class="me"><div><div class="name">' + esc(me) + '</div><div class="sub">' +
      esc([info.club, info.division].filter(Boolean).join(' · ')) + '</div></div>' +
      '<button class="ghost-btn" id="me-change">Keisti</button></div>';

    var next = upcoming[0];
    if (next) {
      var side = sideOf(next, me), other = side === 1 ? 2 : 1;
      var partners = pairNames(next.participants, side).filter(function (n) { return fold(n) !== fold(me); });
      var opp = pairNames(next.participants, other);
      var oppTeam = (other === 1 ? next.team1 : next.team2) || {};
      var big = !next.time ? 'Laikas dar nepaskirtas' : (next.eta && next.eta !== next.time ? '~' + next.eta : next.time);
      html += '<div class="next">' +
        '<div class="label">Kitas tavo mačas' + (next.date && next.date !== nowLocal().date ? ' · ' + esc(dayLabel(next.date)) : '') + '</div>' +
        '<div class="big"' + (next.time ? '' : ' style="font-size:1.6rem"') + '>' + esc(big) + '</div>' +
        (next.eta && next.eta !== next.time ? '<div class="planned">planuota ' + esc(next.time) + ' · laikas apytikslis, gali keistis</div>' : '') +
        (isScheduled(next) ? '<div class="court-big">' + esc(courtLabel(next)) + '</div>' : '') +
        '<div class="vs">' +
          (partners.length ? 'su <b>' + esc(partners.join(', ')) + '</b><br>' : '') +
          'prieš ' + (oppTeam.title && HAS_CLUBS ? '<b>' + esc(oppTeam.title) + '</b> – ' : '') + (opp.length ? esc(opp.map(shortName).join(', ')) : 'nepaskelbta') +
          (next.division ? '<br><span style="color:var(--muted)">' + esc(next.division) + '</span>' : '') +
        '</div>' +
        '<div id="notify-slot"></div>' +
        (isScheduled(next) ? '<div class="queue"><span class="pulse-dot" style="margin-top:6px"></span><span>' + queueText(next) + '</span></div>' : '') +
      '</div>';
    } else {
      html += '<div class="next"><div class="label">Tavo mačai</div><div class="done">' +
        (played.length ? '<svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>Visi tavo mačai sužaisti' : 'Mačų kol kas nėra') + '</div>' +
        (!mine.length && info.rosterOnly ? '<p class="search-hint" style="margin:8px 0 0">' + (info.club ? esc(info.club) + ' dar' : 'Tavo klubas dar') +
          ' nepaskelbė porų. Kai jos atsiras Tournated, čia matysi savo mačus, laiką ir kortą.</p>' : '') + '</div>';
    }

    if (upcoming.length > 1) {
      html += '<div class="section"><span>Vėliau</span><span>' + (upcoming.length - 1) + '</span></div>';
      upcoming.slice(1).forEach(function (m) { html += matchCard(m, me); });
    }
    if (played.length) {
      var wins = played.filter(function (m) { return m.winner_side === sideOf(m, me); }).length;
      html += '<div class="section"><span>Sužaista</span></div>' +
        '<button class="fold-btn" id="mine-played-toggle" aria-expanded="' + showPlayedMine + '"><span>' +
        plural(played.length, 'mačas', 'mačai', 'mačų') + ' · laimėta ' + wins + '</span><span>' + (showPlayedMine ? '▴' : '▾') + '</span></button>';
      if (showPlayedMine) played.forEach(function (m) { html += matchCard(m, me); });
    }
    box.innerHTML = html;
    if (window.PushUI) window.PushUI.render(document.getElementById('notify-slot'), me);
  }

  document.getElementById('mano').addEventListener('click', function (e) {
    var s = e.target.closest('.player-suggestion');
    if (s) { manoQuery = ''; choosePlayer(s.dataset.player); return; }
    if (e.target.closest('#me-change')) { manoQuery = ''; forgetPlayer(); var i = document.getElementById('me-search'); if (i) i.focus(); return; }
    if (e.target.closest('#mine-played-toggle')) { showPlayedMine = !showPlayedMine; renderMano(); }
  });

  /* ---------- "Dabar" ---------- */
  // Club matches: the club line alone does not say who is on court – add the pairs under it.
  function dabarPlayers(m) {
    if (!((m.team1 && m.team1.title) || (m.team2 && m.team2.title))) return '';
    var a = pairNames(m.participants, 1).map(shortName).join(' / ');
    var b = pairNames(m.participants, 2).map(shortName).join(' / ');
    if (!a && !b) return '';
    return '<span class="pp">' + esc(a || 'nepaskelbta') + ' – ' + esc(b || 'nepaskelbta') + '</span>';
  }
  function renderDabar() {
    var box = document.getElementById('dabar');
    var now = nowLocal();
    var today = state.matches.filter(function (m) { return isScheduled(m) && m.date === now.date; });
    if (!today.length) {
      var dates = state.matches.filter(isScheduled).map(function (m) { return m.date; }).sort();
      var future = dates.filter(function (d) { return d > now.date; });
      box.innerHTML = '<p class="empty">' + (future.length
        ? 'Turnyras prasidės ' + esc(dayLabel(future[0]).toLowerCase()) + '.<br>Čia tą dieną matysi, kas vyksta kiekviename korte.'
        : (dates.length ? 'Šiandien mačų nėra – turnyras baigtas.' : 'Grafikas dar nepaskelbtas.')) + '</p>';
      return;
    }
    var courts = {};
    today.forEach(function (m) { (courts[m.court.name] = courts[m.court.name] || []).push(m); });
    var names = Object.keys(courts).sort(function (a, b) { return courtNumber(courts[a][0]) - courtNumber(courts[b][0]); });

    var html = '<div class="section" style="margin-top:4px"><span>Kortai · ' + hhmm(now.min) + '</span></div>';
    names.forEach(function (c) {
      var list = courts[c].slice().sort(byStart);
      var pending = list.filter(function (m) { return !isPlayed(m); });
      var played = list.filter(isPlayed);
      var running = pending[0] && startMin(pending[0]) <= now.min ? pending[0] : null;
      var next = running ? pending[1] : pending[0];
      var rows = '';
      if (running) {
        var slotEnd = startMin(running) + ((pending[1] ? toMin(pending[1].time) - toMin(running.time) : 50) - 5);
        rows += '<div class="crow"><span class="k live">Žaidžia</span><span class="v"><span class="pulse-dot"></span>' +
          esc(sideTitle(running, 1)) + ' – ' + esc(sideTitle(running, 2)) +
          ' <span class="sub">· ' + (running.division ? esc(running.division) + ' · ' : '') + 'nuo ' + esc(running.eta || running.time) + '</span>' + dabarPlayers(running) +
          (now.min > slotEnd ? '<span class="over">Užsitęsė: planuota baigti ' + hhmm(slotEnd) + '</span>' : '') + '</span></div>';
      } else if (pending.length) {
        var last = played[played.length - 1];
        var lastAt = last ? localTimeOf(last.result_seen_at) : null;
        rows += '<div class="crow"><span class="k">Laisvas</span><span class="v">' + (lastAt ? 'Paskutinis baigtas ' + esc(lastAt) : 'Mačas dar neprasidėjo') + '</span></div>';
      }
      if (next) {
        rows += '<div class="crow"><span class="k">Toliau</span><span class="v"><span class="tt">' +
          esc(next.eta && next.eta !== next.time ? '~' + next.eta : next.time) + '</span>' +
          esc(sideTitle(next, 1)) + ' – ' + esc(sideTitle(next, 2)) +
          ' <span class="sub">' + (next.division ? '· ' + esc(next.division) : '') +
          (next.eta && next.eta !== next.time ? ' · planuota ' + esc(next.time) + (toMin(next.eta) < toMin(next.time) ? ', gali anksčiau' : '') : '') + '</span>' + dabarPlayers(next) + '</span></div>';
      }
      if (!pending.length) rows += '<div class="crow"><span class="k">Baigta</span><span class="v sub">Šiame korte šiandien mačų nebeliko</span></div>';
      html += '<div class="court"><h3>' + esc(courtLabel(list[0])) + '</h3>' + rows + '</div>';
    });
    html += '<p class="hint">„~“ laikai apskaičiuojami automatiškai pagal tai, kada teisėjas įveda rezultatus. Mačas niekada nerodomas daugiau nei 15 min anksčiau už planuotą laiką.</p>';
    box.innerHTML = html;
  }

  /* ---------- "Mačai" ---------- */
  var activeDivision = '';
  var showPlayed = null; // null = automatic: hide played while anything is left to play
  var listQuery = '';
  function renderGrid() {
    var box = document.getElementById('grid-results');
    var hit = listQuery ? matcher(listQuery) : null;
    var list = state.matches.filter(function (m) {
      if (activeDivision && divisionGroup(m.division) !== activeDivision) return false;
      if (hit && !hit([pairNames(m.participants, 1).join(' '), pairNames(m.participants, 2).join(' '), (m.team1 || {}).title, (m.team2 || {}).title, m.division].join(' '))) return false;
      return true;
    });
    var played = list.filter(isPlayed), rest = list.filter(function (m) { return !isPlayed(m); });
    // hide played matches automatically only while scheduled ones are still to come
    var show = showPlayed === null ? !rest.some(isScheduled) : showPlayed;
    var html = '';
    if (played.length && rest.some(isScheduled)) {
      html += '<button class="fold-btn" id="played-toggle" aria-expanded="' + show + '"><span>Sužaista ' +
        plural(played.length, 'mačas', 'mačai', 'mačų') + '</span><span>' + (show ? '▴ slėpti' : '▸ rodyti') + '</span></button>';
    }
    html += gridHtml(show ? list : rest, me);
    box.innerHTML = html || '<p class="empty">' + (list.length ? 'Visi mačai sužaisti.' : 'Mačų nerasta.') + '</p>';
  }
  document.getElementById('grid-results').addEventListener('click', function (e) {
    if (e.target.closest('#played-toggle')) {
      var rest = state.matches.some(function (m) { return !isPlayed(m) && isScheduled(m); });
      showPlayed = !(showPlayed === null ? !rest : showPlayed);
      renderGrid();
    }
  });
  document.getElementById('list-filter').addEventListener('input', function (e) { listQuery = e.target.value.trim(); renderGrid(); });
  var pills = document.getElementById('division-pills');
  if (pills) pills.addEventListener('click', function (e) {
    var btn = e.target.closest('.pill');
    if (!btn) return;
    pills.querySelectorAll('.pill').forEach(function (p) { p.classList.toggle('active', p === btn); });
    activeDivision = btn.dataset.division;
    renderGrid();
  });

  /* ---------- "Klubai" ---------- */
  var activeClub = null;
  function renderKlubai() {
    if (!HAS_CLUBS) return;
    if (activeClub === null || CLUB_LIST.indexOf(activeClub) === -1) activeClub = CLUB_LIST[0] || null;
    document.getElementById('club-chips').innerHTML = CLUB_LIST.map(function (c) {
      var active = c === activeClub;
      return '<button class="club-chip' + (active ? ' active' : '') + '" data-club="' + esc(c) + '"' +
        (active ? ' style="--chip-c:' + clubColor(c) + '"' : '') + '>' + clubBadge(c, 26) + esc(c) + '</button>';
    }).join('');
    var list = state.matches.filter(function (m) { return (m.team1 && m.team1.title === activeClub) || (m.team2 && m.team2.title === activeClub); });
    document.getElementById('club-results').innerHTML = gridHtml(list, me) ||
      '<p class="empty">' + (state.ties && state.ties.length ? 'Klubas dar nepaskelbė porų.' : 'Mačų nerasta.') + '</p>';
  }
  if (HAS_CLUBS) document.getElementById('club-chips').addEventListener('click', function (e) {
    var btn = e.target.closest('.club-chip');
    if (btn) { activeClub = btn.dataset.club; renderKlubai(); }
  });

  /* ---------- "Lentelės" ---------- */
  function tallyMatches(matches) {
    var g = {};
    matches.forEach(function (m) {
      [m.team1, m.team2].forEach(function (team) {
        if (team && team.title) g[team.title] = g[team.title] || emptyTally();
      });
      if (!isPlayed(m) || !m.team1 || !m.team2) return;
      var t1 = g[m.team1.title], t2 = g[m.team2.title];
      if (!t1 || !t2) return;
      t1.played++; t2.played++;
      if (m.winner_side === 1) { t1.wins++; t2.losses++; } else if (m.winner_side === 2) { t2.wins++; t1.losses++; }
      (m.sets || []).forEach(function (s) {
        var a = s.side1 ?? 0, b = s.side2 ?? 0;
        t1.setsWon += a > b ? 1 : 0; t1.setsLost += a < b ? 1 : 0;
        t2.setsWon += b > a ? 1 : 0; t2.setsLost += b < a ? 1 : 0;
        t1.gamesWon += a; t1.gamesLost += b; t2.gamesWon += b; t2.gamesLost += a;
      });
    });
    return g;
  }
  function emptyTally() { return { played: 0, wins: 0, losses: 0, setsWon: 0, setsLost: 0, gamesWon: 0, gamesLost: 0 }; }
  // won/lost ratio as Tournated shows it (SR, PR); nothing lost ranks above any finite ratio
  function ratio(won, lost) { return lost ? won / lost : (won ? 1e9 + won : 0); }
  // Tournated's club table scores by TIE (the whole club-vs-club meeting):
  // the club that won more individual matches against an opponent gets 1 point.
  // A drawn meeting (6:6) gives nobody a point — stage I 2026 shows Tournated
  // ranked it that way. The point shows as soon as a club leads (Tadas, 11-27).
  // Matches won per club in each club-vs-club meeting. A finished meeting uses
  // Tournated's own score (it can leave a match out, e.g. stage I BTC–SPK 7:4);
  // one still being played is counted from the results so far.
  function meetingScores(matches, ties) {
    var pairs = {};
    function pair(a, b) { var key = [a, b].sort().join('||'); pairs[key] = pairs[key] || {}; pairs[key][a] = pairs[key][a] || 0; pairs[key][b] = pairs[key][b] || 0; return pairs[key]; }
    var open = {}; // meetings with a match still to play
    matches.forEach(function (m) {
      if (!m.team1 || !m.team2) return;
      var key = [m.team1.title, m.team2.title].sort().join('||');
      if (!isPlayed(m)) { open[key] = true; return; }
      if (!m.winner_side) return;
      var p = pair(m.team1.title, m.team2.title);
      p[m.winner_side === 1 ? m.team1.title : m.team2.title]++;
    });
    (ties || []).forEach(function (t) {
      if (!t.score || !t.team1 || !t.team2 || open[[t.team1.title, t.team2.title].sort().join('||')]) return;
      if (!pairs[[t.team1.title, t.team2.title].sort().join('||')]) return; // nothing played yet
      var p = pair(t.team1.title, t.team2.title);
      p[t.team1.title] = t.score[0]; p[t.team2.title] = t.score[1];
    });
    return pairs;
  }
  function tieRecordByClub(pairs) {
    var rec = {};
    function bump(club, k) { rec[club] = rec[club] || { won: 0, lost: 0 }; rec[club][k]++; }
    Object.keys(pairs).forEach(function (key) {
      var clubs = key.split('||'), wa = pairs[key][clubs[0]] || 0, wb = pairs[key][clubs[1]] || 0;
      if (wa > wb) { bump(clubs[0], 'won'); bump(clubs[1], 'lost'); }
      else if (wb > wa) { bump(clubs[1], 'won'); bump(clubs[0], 'lost'); }
    });
    return rec;
  }
  // Club table order, as Tournated ranks it: meetings won, then the won/lost
  // ratio of matches, then of sets (SR), then of games (PR).
  function byClubRank(a, b) {
    return b.tiePoints - a.tiePoints ||
      ratio(b.wins, b.losses) - ratio(a.wins, a.losses) ||
      ratio(b.setsWon, b.setsLost) - ratio(a.setsWon, a.setsLost) ||
      ratio(b.gamesWon, b.gamesLost) - ratio(a.gamesWon, a.gamesLost);
  }
  function standingsTableHtml(title, tally, ties) {
    var rows = Object.keys(tally).map(function (club) {
      var t = (ties && ties[club]) || { won: 0, lost: 0 };
      return Object.assign({ club: club, tiePoints: t.won, tieLost: t.lost }, tally[club]);
    });
    if (!rows.length) return '';
    rows.sort(ties ? byClubRank
      : function (a, b) { return b.wins - a.wins || (b.setsWon - b.setsLost) - (a.setsWon - a.setsLost); });
    var html = '<div class="division-block"><h3 class="division-title">' + esc(title) + '</h3><div class="standings-list">';
    rows.forEach(function (r, i) {
      var diff = r.setsWon - r.setsLost;
      html += '<div class="standing-row' + (ties ? ' club-row' : '') + '"><span class="standing-rank' + (i === 0 ? ' top' : '') + '">' + (i + 1) + '</span>' +
        clubBadge(r.club, 26) + '<span class="standing-club">' + esc(r.club) + '</span><span class="standing-stats">' +
        (ties ? '<span>' + r.tiePoints + '<em>tšk.</em></span><span>' + r.tiePoints + '–' + r.tieLost + '<em>susit.</em></span>' : '') +
        '<span>' + r.wins + '–' + r.losses + '<em>laim.–pral.</em></span>' +
        '<span class="standing-diff' + (diff > 0 ? ' pos' : '') + '">' + (diff > 0 ? '+' : '') + diff + '<em>setai</em></span></span></div>';
    });
    return html + '</div></div>';
  }
  // Every club-vs-club meeting: its score and, folded, the matches behind it.
  function meetingsHtml(pairs) {
    var list = [], seen = {};
    function add(a, b) { var key = [a, b].sort().join('||'); if (seen[key]) return; seen[key] = true; list.push({ a: a, b: b, key: key, matches: [] }); }
    (state.ties || []).forEach(function (t) { if (t.team1 && t.team2) add(t.team1.title, t.team2.title); });
    state.matches.forEach(function (m) { if (m.team1 && m.team2) add(m.team1.title, m.team2.title); });
    var byKey = {}; list.forEach(function (x) { byKey[x.key] = x; });
    state.matches.forEach(function (m) { if (m.team1 && m.team2) byKey[[m.team1.title, m.team2.title].sort().join('||')].matches.push(m); });
    if (!list.length) return '';
    var html = '<div class="division-block"><h3 class="division-title">Susitikimai</h3><div class="meetings">';
    list.forEach(function (x) {
      var sc = pairs[x.key] || {}, wa = sc[x.a] || 0, wb = sc[x.b] || 0;
      var played = x.matches.filter(isPlayed).length, total = x.matches.length;
      var status = !played ? 'dar nežaista' : played < total ? 'vyksta · ' + played + ' iš ' + total : 'baigtas';
      x.matches.sort(function (m, n) { return ((m.date || '') + (m.time || '')).localeCompare((n.date || '') + (n.time || '')); });
      html += '<details class="meeting"><summary>' +
        '<span class="meeting-club">' + clubBadge(x.a, 24) + '<span' + (played && wa > wb ? ' class="win"' : '') + '>' + esc(x.a) + '</span></span>' +
        '<span class="meeting-score">' + (played ? wa + ':' + wb : '–') + '</span>' +
        '<span class="meeting-club right"><span' + (played && wb > wa ? ' class="win"' : '') + '>' + esc(x.b) + '</span>' + clubBadge(x.b, 24) + '</span>' +
        '<span class="meeting-status">' + status + (total ? ' · mačai <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' : '') + '</span></summary><ol class="meeting-matches">';
      x.matches.forEach(function (m) {
        var aSide = m.team1.title === x.a ? 1 : 2, bSide = 3 - aSide;
        var sets = (m.sets || []).map(function (st) { return (aSide === 1 ? st.side1 : st.side2) + ':' + (aSide === 1 ? st.side2 : st.side1); }).join(' ');
        function names(side) { var n = pairNames(m.participants, side).map(shortName).join(', '); return '<span' + (m.winner_side === side ? ' class="win"' : '') + '>' + esc(n || '—') + '</span>'; }
        html += '<li><span class="mm-div">' + esc(m.division || '') + '</span>' + names(aSide) +
          '<span class="mm-sets">' + esc(sets || (m.time ? m.time : '')) + '</span>' + names(bSide) + '</li>';
      });
      html += '</ol></details>';
    });
    return html + '</div></div>';
  }
  // Tournated's stage ranking → league points (12/9/7/4), shown once published.
  function leaguePointsHtml() {
    var rows = state.leaguePoints || [];
    if (!rows.length) return '';
    var html = '<div class="division-block"><h3 class="division-title">Lygos taškai už etapą</h3><div class="standings-list">';
    rows.forEach(function (r, i) {
      html += '<div class="standing-row"><span class="standing-rank' + (i === 0 ? ' top' : '') + '">' + esc(r.position || i + 1) + '</span>' +
        clubBadge(r.club, 26) + '<span class="standing-club">' + esc(r.club) + '</span><span class="standing-stats">' +
        '<span>+' + esc(r.points) + '<em>lygos tšk.</em></span></span></div>';
    });
    return html + '</div></div>';
  }
  function renderStandings() {
    var box = document.getElementById('standings-results');
    if (!HAS_CLUBS) {
      box.innerHTML = tableCategories().some(function (c) { return c.groups.length; }) ? '' : '<p class="empty">Grupės pasirodys, kai bus paskelbtos.</p>';
      return;
    }
    if (!state.matches.length) { box.innerHTML = '<p class="empty">Grupės pasirodys, kai bus paskelbti mačai.</p>'; return; }
    // "Klubų įskaita" – not the Tournated group name ("Bendra"), which also
    // exists as a category and made two tables with the same title.
    // Clubs that haven't published pairs yet still belong in the table (0 points).
    var clubTally = tallyMatches(state.matches);
    (state.ties || []).forEach(function (t) {
      [t.team1, t.team2].forEach(function (team) {
        if (team && team.title && !clubTally[team.title]) clubTally[team.title] = emptyTally();
      });
    });
    // club match record = sum over its meetings (so it agrees with Tournated)
    var pairs = meetingScores(state.matches, state.ties);
    Object.keys(clubTally).forEach(function (c) { clubTally[c].wins = 0; clubTally[c].losses = 0; });
    Object.keys(pairs).forEach(function (key) {
      var clubs = key.split('||');
      [0, 1].forEach(function (i) {
        var me = clubs[i], other = clubs[1 - i];
        if (!clubTally[me]) clubTally[me] = emptyTally();
        clubTally[me].wins += pairs[key][me] || 0; clubTally[me].losses += pairs[key][other] || 0;
      });
    });
    var html = standingsTableHtml('Klubų įskaita', clubTally, tieRecordByClub(pairs)) +
      '<p class="legend">tšk. – klubo taškai: 1 už laimėtą susitikimą su kitu klubu (lygiosios 6:6 – 0) · susit. – laimėti ir pralaimėti susitikimai · ' +
      'laim.–pral. – laimėti ir pralaimėti mačai · setai – laimėtų ir pralaimėtų setų skirtumas. Esant vienodiems taškams lemia mačų, setų ir geimų santykis.</p>' +
      leaguePointsHtml();
    // Club league: what matters is club vs club, so the meetings replace the
    // per-category tables (Tadas, 11-27).
    html += meetingsHtml(pairs);
    box.innerHTML = html;
  }

  /* ---------- groups & brackets (Tournated draws) ---------- */
  // One chip per category: its knockout draw and/or its player groups. A club
  // league's single group is already the club table above, so it is skipped.
  var activeCat = null, activeBt = {};
  function matchesById() { var o = {}; state.matches.forEach(function (m) { o[m.match_id] = m; }); return o; }
  function tableCategories() {
    var cats = [], byKey = {};
    function cat(key, title) {
      if (!byKey[key]) { byKey[key] = { key: key, title: title, draws: [], groups: [] }; cats.push(byKey[key]); }
      return byKey[key];
    }
    (state.draws || []).forEach(function (d) { cat('c' + (d.category_id ?? d.draw_id), d.title).draws.push(d); });
    if (HAS_GROUP_TABLES) (state.groups || []).forEach(function (g) {
      if (g.match_ids && g.match_ids.length) cat('c' + (g.category_id ?? g.group_id), g.name).groups.push(g);
    });
    return cats;
  }
  function isMeName(n) { return !!me && fold(n) === fold(me); }
  function pairLabel(names) { return names.map(shortName).join(' / '); }
  function roundRank(r) {
    var m = /^R(\d+)$/i.exec(r || '');
    if (m) return +m[1];
    if (/quarter/i.test(r)) return 97;
    if (/semi/i.test(r)) return 98;
    if (/^final$/i.test(r)) return 99;
    return 50;
  }
  function isPlacement(r) { return /place/i.test(r || ''); }
  function btLabel(bt) { return !bt || bt === 'main' ? 'Pagrindinis' : bt.replace('-', '–') + ' v.'; }
  function colLabel(round, count, bt) {
    var main = !bt || bt === 'main', range = main ? '' : bt.replace('-', '–') + ' v. ';
    if (main) {
      if (/^final$/i.test(round) || count === 1) return 'Finalas';
      if (/semi/i.test(round) || count === 2) return 'Pusfinalis';
      if (/quarter/i.test(round) || count === 4) return 'Ketvirtfinalis';
      if (count === 8) return 'Aštuntfinalis';
      return roundRank(round) + ' ratas';
    }
    if (count === 1) return 'Dėl ' + parseInt(bt, 10) + ' vietos';
    if (count === 2) return range + 'pusfinalis';
    if (count === 4) return range + 'ketvirtfinalis';
    return range + roundRank(round) + ' ratas';
  }
  function drawEntries(d) {
    var byId = matchesById(), list = [];
    (d.match_ids || []).forEach(function (id) {
      var m = byId[id];
      if (m) list.push({ id: id, m: m, round: m.round, bt: m.bracket_type || 'main', sides: [pairNames(m.participants, 1), pairNames(m.participants, 2)], winner: m.winner_side, sets: m.sets || [] });
    });
    (d.slots || []).forEach(function (s) {
      list.push({ id: s.match_id, round: s.round, bt: s.bracket_type || 'main', sides: [s.side1 || [], s.side2 || []], winner: s.winner_side, sets: [], bye: s.bye });
    });
    return list;
  }
  function entryLive(e) { return !!(e.m && e.m.is_match_in_progress && !isPlayed(e.m) && e.m.date === nowLocal().date); }
  function entryDone(e) { return !!(e.winner || (e.sets && e.sets.length) || (e.m && isPlayed(e.m))); }
  function bmHtml(e) {
    var all = e.sides[0].concat(e.sides[1]);
    var mine = all.some(isMeName), live = entryLive(e), meta = '';
    if (e.m && !isPlayed(e.m) && e.m.time) {
      meta = (e.m.date && e.m.date !== nowLocal().date ? dayLabel(e.m.date) + ' ' : '') +
        (e.m.eta && e.m.eta !== e.m.time ? '~' + e.m.eta : e.m.time) + (isScheduled(e.m) ? ' · ' + courtLabel(e.m) : '');
    }
    var html = '<div class="bm' + (mine ? ' mine' : '') + (live ? ' live' : '') + '">';
    if (live) html += '<div class="bmeta">● Vyksta · ' + esc(courtLabel(e.m)) + '</div>';
    else if (meta) html += '<div class="bmeta">' + esc(meta) + '</div>';
    [0, 1].forEach(function (i) {
      var side = i + 1, names = e.sides[i], other = e.sides[1 - i];
      var cls = e.winner ? (e.winner === side ? ' won' : ' lost') : '';
      if (names.some(isMeName)) cls += ' isme';
      var label = names.length ? esc(pairLabel(names))
        : '<span class="bye">' + (e.bye && other.length ? 'laisvas ratas' : 'laukiama') + '</span>';
      var sets = (e.sets || []).map(function (s) {
        var a = side === 1 ? s.side1 : s.side2, b = side === 1 ? s.side2 : s.side1;
        return '<span class="' + ((a ?? 0) > (b ?? 0) ? 'w' : '') + '">' + esc(a ?? '') + '</span>';
      }).join('');
      html += '<div class="bs' + cls + '"><span class="bn">' + label + '</span><span class="bsets">' + sets + '</span></div>';
    });
    return html + '</div>';
  }
  function bracketHtml(entries, bt) {
    var rounds = {};
    entries.filter(function (e) { return !isPlacement(e.round); }).forEach(function (e) { (rounds[e.round || '?'] = rounds[e.round || '?'] || []).push(e); });
    var order = Object.keys(rounds).sort(function (a, b) { return roundRank(a) - roundRank(b); });
    var html = '', focus = -1;
    if (order.length) {
      var h0 = rounds[order[0]].length * 82;
      html = '<div class="bracket-scroll"><div class="bracket">';
      order.forEach(function (r, ci) {
        var list = rounds[r].sort(function (a, b) { return a.id - b.id; });
        if (list.some(function (e) { return entryDone(e) || entryLive(e); })) focus = ci;
        var h = Math.max(82, Math.round(h0 / list.length));
        html += '<div class="bcol' + (ci === 0 ? ' first' : '') + '"><div class="bhead">' + esc(colLabel(r, list.length, bt)) + '</div>';
        var joined = list.length > 1 && list.length % 2 === 0 && ci < order.length - 1;
        for (var i = 0; i < list.length; i += joined ? 2 : 1) {
          var group = joined ? list.slice(i, i + 2) : [list[i]];
          html += '<div class="pairbox' + (joined ? ' joined' : '') + '">' + group.map(function (e) {
            return '<div class="slot" style="height:' + h + 'px">' + bmHtml(e) + '</div>';
          }).join('') + '</div>';
        }
        html += '</div>';
      });
      html += '</div></div>';
    }
    var places = entries.filter(function (e) { return isPlacement(e.round); }).sort(function (a, b) { return a.id - b.id; });
    if (places.length) {
      html += '<div class="placement">' + places.map(function (e) {
        var n = (/(\d+)/.exec(e.round) || [])[1];
        return '<div><div class="bhead">Dėl ' + esc(n || '') + ' vietos</div>' + bmHtml(e) + '</div>';
      }).join('') + '</div>';
    }
    return { html: html, focus: focus };
  }
  function championHtml(entries) {
    var main = entries.filter(function (e) { return e.bt === 'main' && !isPlacement(e.round); });
    if (!main.length) return '';
    var top = Math.max.apply(null, main.map(function (e) { return roundRank(e.round); }));
    var last = main.filter(function (e) { return roundRank(e.round) === top; });
    if (last.length !== 1 || !last[0].winner) return '';
    var names = last[0].sides[last[0].winner - 1];
    if (!names.length) return '';
    return '<div class="champ"><span class="cup" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg></span><div><div class="l">Nugalėtojai</div><div class="n">' + esc(names.join(' / ')) + '</div></div></div>';
  }
  function groupTableHtml(g) {
    var byId = matchesById(), rows = {};
    (g.match_ids || []).forEach(function (id) {
      var m = byId[id]; if (!m) return;
      var keys = [1, 2].map(function (side) {
        var names = pairNames(m.participants, side); if (!names.length) return null;
        var k = names.map(fold).sort().join('|');
        rows[k] = rows[k] || { names: names, p: 0, w: 0, l: 0, sw: 0, sl: 0, gw: 0, gl: 0 };
        return k;
      });
      if (!keys[0] || !keys[1] || !isPlayed(m) || !m.winner_side) return;
      var a = rows[keys[0]], b = rows[keys[1]];
      a.p++; b.p++;
      if (m.winner_side === 1) { a.w++; b.l++; } else { b.w++; a.l++; }
      (m.sets || []).forEach(function (s) {
        var x = s.side1 ?? 0, y = s.side2 ?? 0;
        a.gw += x; a.gl += y; b.gw += y; b.gl += x;
        if (x > y) { a.sw++; b.sl++; } else if (y > x) { b.sw++; a.sl++; }
      });
    });
    var list = Object.keys(rows).map(function (k) { return rows[k]; });
    if (!list.length) return '';
    list.sort(function (a, b) { return b.w - a.w || (b.sw - b.sl) - (a.sw - a.sl) || (b.gw - b.gl) - (a.gw - a.gl); });
    function d(n) { return (n > 0 ? '+' : '') + n; }
    var html = '<div class="division-block"><h3 class="division-title">' + esc(g.name || 'Grupė') + '</h3><div class="gtable-wrap"><table class="gtable">' +
      '<thead><tr><th>#</th><th>Pora</th><th>Ž</th><th>L–P</th><th>Setai</th><th>Geim.</th></tr></thead><tbody>';
    list.forEach(function (r, i) {
      var mine = r.names.some(isMeName);
      html += '<tr class="' + (i === 0 ? 'top' : '') + (mine ? ' isme' : '') + '"><td class="pos">' + (i + 1) + '</td><td class="pn">' + esc(pairLabel(r.names)) +
        '</td><td>' + r.p + '</td><td class="pts">' + r.w + '–' + r.l + '</td><td>' + d(r.sw - r.sl) + '</td><td>' + d(r.gw - r.gl) + '</td></tr>';
    });
    return html + '</tbody></table></div></div>';
  }
  function catHasMe(c) {
    if (!me) return false;
    var byId = matchesById(), ids = [];
    c.draws.forEach(function (d) { ids = ids.concat(d.match_ids || []); });
    c.groups.forEach(function (g) { ids = ids.concat(g.match_ids || []); });
    return ids.some(function (id) { return byId[id] && sideOf(byId[id], me) > 0; });
  }
  function renderBrackets() {
    var box = document.getElementById('brackets-results');
    var cats = tableCategories().filter(function (c) { return c.draws.length; });
    if (!cats.length) { box.innerHTML = '<p class="empty">Lentelės pasirodys, kai bus paskelbtos.</p>'; return; }
    var cur = cats.filter(function (c) { return c.key === activeCat; })[0] || cats.filter(catHasMe)[0] || cats[0];
    activeCat = cur.key;
    var html = '';
    if (cats.length > 1) {
      html += '<div class="cat-chips">' + cats.map(function (c) {
        return '<button class="cat-chip' + (c.key === cur.key ? ' active' : '') + '" data-cat="' + esc(c.key) + '">' + esc(c.title) + '</button>';
      }).join('') + '</div>';
    } else {
      html += '<h3 class="division-title">' + esc(cur.title) + '</h3>';
    }
    var focus = -1;
    cur.draws.forEach(function (d) {
      var entries = drawEntries(d), bts = [];
      entries.forEach(function (e) { if (bts.indexOf(e.bt) === -1) bts.push(e.bt); });
      if (!bts.length) return;
      bts.sort(function (a, b) { return (a === 'main' ? 0 : parseInt(a, 10)) - (b === 'main' ? 0 : parseInt(b, 10)); });
      var bt = bts.indexOf(activeBt[d.draw_id]) !== -1 ? activeBt[d.draw_id] : bts[0];
      if (bt === 'main') html += championHtml(entries);
      if (bts.length > 1) {
        html += '<div class="seg">' + bts.map(function (b) {
          return '<button class="' + (b === bt ? 'active' : '') + '" data-draw="' + esc(d.draw_id) + '" data-bt="' + esc(b) + '">' + esc(btLabel(b)) + '</button>';
        }).join('') + '</div>';
      }
      var br = bracketHtml(entries.filter(function (e) { return e.bt === bt; }), bt);
      html += br.html; focus = br.focus;
    });
    box.innerHTML = html;
    var scroller = box.querySelector('.bracket-scroll');
    if (scroller) scroller.dataset.focus = focus;
    focusBracket();
  }
  // Open on the newest round that has been played (or is being played now),
  // and keep the chosen category chip in view. Needs the panel to be visible.
  function focusBracket() {
    var box = document.getElementById('brackets-results');
    if (!box.offsetParent) return;
    var chip = box.querySelector('.cat-chip.active');
    if (chip) chip.parentNode.scrollLeft = chip.offsetLeft - chip.parentNode.offsetLeft - 14;
    var scroller = box.querySelector('.bracket-scroll'), focus = scroller ? +scroller.dataset.focus : -1;
    if (scroller && focus > 0) {
      var col = scroller.querySelectorAll('.bcol')[focus];
      if (col) scroller.scrollLeft = Math.max(0, col.offsetLeft - scroller.querySelector('.bracket').offsetLeft + col.offsetWidth - scroller.clientWidth + 28);
    }
  }
  var activeGCat = null;
  function renderGroupTables() {
    var box = document.getElementById('groups-results');
    if (!box) return;
    var cats = tableCategories().filter(function (c) { return c.groups.length; });
    if (!cats.length) { box.innerHTML = ''; return; }
    var cur = cats.filter(function (c) { return c.key === activeGCat; })[0] || cats.filter(catHasMe)[0] || cats[0];
    activeGCat = cur.key;
    var html = HAS_CLUBS ? '<h3 class="section-sub">Žaidėjų grupės</h3>' : '';
    if (cats.length > 1) {
      html += '<div class="cat-chips">' + cats.map(function (c) {
        return '<button class="cat-chip' + (c.key === cur.key ? ' active' : '') + '" data-gcat="' + esc(c.key) + '">' + esc(c.title) + '</button>';
      }).join('') + '</div>';
    } else {
      html += '<h3 class="division-title">' + esc(cur.title) + '</h3>';
    }
    cur.groups.forEach(function (g) { html += groupTableHtml(g); });
    box.innerHTML = html;
  }
  var groupsBox = document.getElementById('groups-results');
  if (groupsBox) groupsBox.addEventListener('click', function (e) {
    var chip = e.target.closest('.cat-chip');
    if (chip) { activeGCat = chip.dataset.gcat; renderGroupTables(); }
  });
  document.getElementById('brackets-results').addEventListener('click', function (e) {
    var chip = e.target.closest('.cat-chip');
    if (chip) { activeCat = chip.dataset.cat; renderBrackets(); return; }
    var seg = e.target.closest('.seg button');
    if (seg) { activeBt[seg.dataset.draw] = seg.dataset.bt; renderBrackets(); }
  });

  /* ---------- header, tabs, refresh ---------- */
  // One day: "Lapkričio 27 d., penktadienis". Several days: today's label while
  // it is on, otherwise the span ("Lapkričio 27–28 d.").
  function tourDateLabel() {
    var days = state.matches.map(function (m) { return m.date; }).filter(Boolean).sort();
    var first = days[0] || TOURNAMENT_DATE, last = days[days.length - 1] || TOURNAMENT_DATE;
    if (first === last) return dayLabel(first);
    var today = nowLocal().date;
    if (today >= first && today <= last) return dayLabel(today);
    var a = dayLabel(first).split(',')[0], b = dayLabel(last).split(',')[0];
    return first.slice(0, 7) === last.slice(0, 7) ? a.replace(/ d\.$/, '–' + (+last.slice(8)) + ' d.') : a + ' – ' + b;
  }
  function renderHeader() {
    if (TOURNAMENT_DATE) document.getElementById('tour-date').textContent = tourDateLabel();
    var el = document.getElementById('fresh'), txt = document.getElementById('fresh-text');
    if (!state.syncedAt) { txt.textContent = 'Duomenys dar nesinchronizuoti'; el.classList.add('stale'); return; }
    var mins = Math.max(0, Math.round((Date.now() - new Date(state.syncedAt).getTime()) / 60000));
    var label = mins < 1 ? 'ką tik' : (mins < 60 ? 'prieš ' + mins + ' min' : 'atnaujinta ' + localTimeOf(state.syncedAt));
    var playing = state.matches.some(function (m) { return m.date === nowLocal().date && !isPlayed(m); });
    var stale = !state.lastFetchOk || (playing && mins > 5);
    txt.textContent = !state.lastFetchOk ? 'Nėra ryšio · ' + label : label;
    el.classList.toggle('stale', stale);
  }

  function renderAll() {
    indexClubImages(state.matches); indexClubImages(state.ties || []);
    renderHeader(); renderMano(); renderDabar(); renderGrid(); renderKlubai(); renderStandings(); renderGroupTables(); renderBrackets();
  }

  var TABS = Array.prototype.map.call(document.querySelectorAll('.nav-btn'), function (b) { return b.dataset.tab; });
  function tabFromHash() { var t = location.hash.replace('#', ''); return TABS.indexOf(t) >= 0 ? t : 'mano'; }
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function goTab(tab) {
    // compare with the tab on screen, not the URL: they can differ (old link, back button mid-transition)
    var shown = document.querySelector('.nav-btn.active');
    if (shown && shown.dataset.tab === tab) { window.scrollTo(0, 0); return; }
    try { var u = new URL(location.href); u.hash = tab === 'mano' ? '' : tab; history.pushState({ tab: tab }, '', u); } catch (e) {}
    if (document.startViewTransition && !reduceMotion) document.startViewTransition(function () { switchTab(tab); });
    else switchTab(tab);
  }
  window.addEventListener('popstate', function () { switchTab(tabFromHash()); });
  function switchTab(tab) {
    document.querySelectorAll('.nav-btn').forEach(function (b) {
      var on = b.dataset.tab === tab;
      b.classList.toggle('active', on);
      if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
    });
    document.querySelectorAll('[data-panel]').forEach(function (p) { p.classList.toggle('active', p.dataset.panel === tab); });
    window.scrollTo(0, 0);
    if (tab === 'lenteles') focusBracket();
  }
  document.querySelectorAll('.nav-btn').forEach(function (btn) { btn.addEventListener('click', function () { goTab(btn.dataset.tab); }); });

  function scoreMap(list) {
    var map = {};
    (list || []).forEach(function (m) { if (m.match_id) map[m.match_id] = JSON.stringify(m.sets || []) + '|' + (m.winner_side || ''); });
    return map;
  }
  function flashChanged(before, after) {
    Object.keys(after).forEach(function (id) {
      if (!(id in before) || before[id] === after[id]) return;
      document.querySelectorAll('[data-m="' + id + '"] .set-scores').forEach(function (el) { el.classList.add('flash'); });
    });
  }

  // Re-render only when the data really changed (no flicker every 45 s);
  // refresh at once when the phone comes back to the page.
  var lastBody = null;
  function refresh() {
    fetch(DATA_URL, { cache: 'no-store' }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); }).then(function (body) {
      state.lastFetchOk = true;
      if (body === lastBody) { renderHeader(); return; }
      lastBody = body;
      var json = JSON.parse(body);
      var before = scoreMap(state.matches);
      state.matches = json.matches || [];
      state.groups = json.groups || state.groups;
      state.draws = json.draws || state.draws;
      state.ties = json.ties || state.ties;
      state.leaguePoints = json.league_points || state.leaguePoints;
      state.syncedAt = json.synced_at;
      var typing = document.activeElement && document.activeElement.id === 'me-search';
      if (typing) { renderHeader(); renderDabar(); renderGrid(); renderKlubai(); renderStandings(); renderGroupTables(); renderBrackets(); } else renderAll();
      flashChanged(before, scoreMap(state.matches));
    }).catch(function () { state.lastFetchOk = false; renderHeader(); });
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') refresh(); });
  setInterval(function () { if (document.visibilityState === 'visible') refresh(); }, 45000);
  setInterval(function () { renderHeader(); renderDabar(); }, 30000);

  renderAll();
  if (tabFromHash() !== 'mano') switchTab(tabFromHash());
})();
</script>
</body>
</html>
