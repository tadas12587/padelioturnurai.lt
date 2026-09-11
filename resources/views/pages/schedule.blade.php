@php
    $tName = $tournament['name'] ?? 'Turnyras';
    $tDate = $tournament['date'] ?? null;
@endphp
<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Grafikas · {{ $tName }}</title>
<meta name="theme-color" content="#0A2226">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap">
<style>
  /*
    Padel-court palette: a deep "under the lights" court teal instead of the
    generic near-black-plus-purple AI default, with a single padel-ball
    yellow-green as the one accent (live state, winner mark, active nav,
    the "now" mark on the day rail) — not decoration, it always means
    "this is the live/active/winning thing". Club identity is a separate,
    deliberately multi-hue system (see CLUB_COLORS in the script) because
    telling clubs apart at a glance is the actual usability problem here.
  */
  :root {
    --ground: #0A2226;
    --surface: #0E2C30;
    --surface-2: #123539;
    --line: rgba(238,244,242,0.09);
    --ink: #EAF3F1;
    --ink-soft: #A9C4BE;
    --muted: #6E8B85;
    --ball: #D9F45A;
    --ball-ink: #17230a;
    --sans: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --display: 'Archivo', var(--sans);
  }
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html { scroll-behavior: smooth; }
  body {
    margin: 0; background: var(--ground); color: var(--ink); font-family: var(--sans);
    -webkit-font-smoothing: antialiased; padding-bottom: 76px;
  }
  a { color: inherit; }
  .wrap { max-width: 620px; margin: 0 auto; padding: 0 16px; }

  /* ---------- header ---------- */
  header.top {
    padding: 16px 0 12px; display: flex; justify-content: space-between; align-items: center;
    gap: 10px;
  }
  .brand {
    font-family: var(--display); font-weight: 700; letter-spacing: 0.01em; font-size: 0.92rem;
    line-height: 1.25; color: var(--ink);
  }
  .home-link {
    font-size: 0.78rem; color: var(--muted); text-decoration: none; white-space: nowrap;
    border: 1px solid var(--line); border-radius: 999px; padding: 6px 12px; flex: none;
  }
  .home-link:hover { color: var(--ink); border-color: var(--ball); }

  .eyebrow {
    font-family: var(--display); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.14em;
    text-transform: uppercase; color: var(--ball); margin: 10px 0 4px;
  }
  h1 {
    font-family: var(--display); font-size: clamp(1.9rem, 9vw, 2.5rem); font-weight: 800;
    margin: 0 0 8px; letter-spacing: -0.02em; line-height: 1;
  }
  .lede { color: var(--ink-soft); font-size: 0.92rem; line-height: 1.5; margin: 0 0 18px; max-width: 46ch; }

  /* ---------- day rail (signature) ---------- */
  .day-rail-wrap { margin-bottom: 16px; }
  .day-rail { display: flex; align-items: center; gap: 8px; }
  .rail-label {
    font-family: var(--display); font-size: 0.68rem; font-weight: 700; color: var(--muted);
    font-variant-numeric: tabular-nums; flex: none;
  }
  .rail-track {
    position: relative; flex: 1; height: 22px; display: flex; align-items: center;
  }
  .rail-track::before {
    content: ""; position: absolute; left: 0; right: 0; top: 50%; height: 2px;
    background: var(--line); transform: translateY(-50%);
  }
  .rail-tick {
    position: absolute; top: 50%; width: 7px; height: 7px; border-radius: 50%;
    background: var(--surface-2); border: 1.5px solid var(--muted);
    transform: translate(-50%, -50%); cursor: pointer;
  }
  .rail-tick.has-live { background: var(--ball); border-color: var(--ball); animation: pulse 1.6s ease-in-out infinite; }
  .rail-tick.has-played { border-color: var(--ink-soft); }
  .rail-now {
    position: absolute; top: 50%; width: 12px; height: 12px; border-radius: 50%;
    background: var(--ball); transform: translate(-50%, -50%); box-shadow: 0 0 0 4px rgba(217,244,90,0.22);
  }

  /* ---------- compact stat strip ---------- */
  .stat-strip {
    display: flex; flex-wrap: wrap; gap: 4px 0; font-family: var(--display); font-size: 0.78rem;
    color: var(--ink-soft); margin-bottom: 14px; font-weight: 600;
  }
  .stat-strip b { color: var(--ink); font-variant-numeric: tabular-nums; }
  .stat-strip .sep { color: var(--muted); margin: 0 8px; font-weight: 400; }

  .synced { font-size: 0.74rem; color: var(--muted); margin-bottom: 16px; display: flex; align-items: center; gap: 6px; }
  .live-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ball); display: inline-block; box-shadow: 0 0 0 3px rgba(217,244,90,0.18); }

  /* ---------- search ---------- */
  .search-box { position: relative; margin-bottom: 16px; }
  .search-box svg { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); }
  .search-box input {
    width: 100%; font: inherit; font-size: 0.95rem; background: var(--surface);
    border: 1px solid var(--line); border-radius: 12px; padding: 13px 14px 13px 40px; color: var(--ink);
  }
  .search-box input::placeholder { color: var(--muted); }
  .search-box input:focus { outline: none; border-color: var(--ball); box-shadow: 0 0 0 3px rgba(217,244,90,0.15); }

  /* ---------- pills / club chips ---------- */
  .pills, .club-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
  .pill {
    font-family: var(--display); font-size: 0.78rem; font-weight: 600; color: var(--ink-soft);
    background: var(--surface); border: 1px solid var(--line); border-radius: 999px;
    padding: 8px 14px; cursor: pointer;
  }
  .pill.active { color: var(--ball-ink); background: var(--ball); border-color: var(--ball); }

  .club-chip {
    display: flex; align-items: center; gap: 8px; font-family: var(--display); font-size: 0.82rem;
    font-weight: 600; background: var(--surface); border: 1px solid var(--line); border-radius: 999px;
    padding: 6px 14px 6px 6px; cursor: pointer; color: var(--ink-soft);
  }
  .club-chip.active { color: var(--ink); background: var(--surface-2); border-color: var(--chip-c, var(--ink-soft)); }

  /* ---------- match card ---------- */
  .time-head {
    font-family: var(--display); font-weight: 700; font-size: 1rem; margin: 22px 0 8px;
    display: flex; align-items: baseline; gap: 8px; scroll-margin-top: 14px;
  }
  .time-head .n { font-size: 0.68rem; font-weight: 600; color: var(--muted); }

  .match {
    background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
    padding: 12px 14px 13px; margin-bottom: 10px;
  }
  .match-top {
    display: flex; align-items: center; gap: 8px; margin-bottom: 9px;
    font-family: var(--display); font-size: 0.72rem; font-weight: 700; color: var(--muted);
    letter-spacing: 0.03em; text-transform: uppercase;
  }
  .match-top .spacer { flex: 1; }
  .badge {
    font-family: var(--display); font-size: 0.66rem; font-weight: 700; letter-spacing: 0.03em;
    padding: 3px 9px; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--line);
    color: var(--ink-soft); white-space: nowrap; text-transform: none;
  }
  .badge.live { background: var(--ball); border-color: var(--ball); color: var(--ball-ink); animation: pulse 1.6s ease-in-out infinite; }
  .badge.division { color: var(--muted); }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }

  .match-row { display: flex; align-items: center; gap: 10px; padding: 4px 0; }
  .chip {
    width: 30px; height: 30px; border-radius: 10px; flex: none; display: flex; align-items: center;
    justify-content: center; font-family: var(--display); font-size: 0.72rem; font-weight: 800;
    color: rgba(10,34,38,0.82); position: relative; overflow: hidden;
  }
  .chip img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; background: #fff; }
  .row-text { min-width: 0; flex: 1; }
  .row-text b { display: block; font-size: 0.9rem; font-weight: 600; color: var(--ink-soft); line-height: 1.25; }
  .row-text span { display: block; font-size: 0.86rem; color: var(--muted); line-height: 1.3; overflow-wrap: anywhere; }
  .match-row.winner .row-text b { color: var(--ink); }
  .match-row.winner .row-text span { color: var(--ink-soft); }
  .set-scores { display: flex; gap: 9px; flex: none; padding: 0 4px; }
  .set-cell {
    font-family: var(--display); font-weight: 700; font-size: 1rem; color: var(--ink-soft);
    font-variant-numeric: tabular-nums; width: 15px; text-align: center;
  }
  .set-cell.won { color: var(--ball); }

  /* ---------- standings ---------- */
  .division-block { margin-bottom: 26px; }
  .division-title { font-family: var(--display); font-size: 1rem; font-weight: 700; margin: 0 0 10px; }
  .standings-list { display: flex; flex-direction: column; gap: 6px; }
  .standing-row {
    display: flex; align-items: center; gap: 10px; background: var(--surface);
    border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px;
  }
  .standing-rank {
    font-family: var(--display); font-weight: 800; font-size: 0.92rem; color: var(--muted);
    width: 18px; text-align: center; flex: none;
  }
  .standing-rank.top { color: var(--ball); }
  .standing-club { flex: 1; min-width: 0; font-weight: 600; font-size: 0.92rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .standing-stats {
    display: flex; gap: 10px; font-family: var(--display); font-size: 0.78rem; color: var(--ink-soft);
    font-variant-numeric: tabular-nums; flex: none;
  }
  .standing-stats em { font-style: normal; color: var(--muted); font-size: 0.66rem; display: block; text-align: center; }
  .standing-diff { min-width: 34px; text-align: right; }
  .standing-diff.pos { color: var(--ball); }

  .empty { color: var(--muted); font-size: 0.9rem; padding: 36px 0; text-align: center; }

  footer { border-top: 1px solid var(--line); margin-top: 40px; padding: 16px 0 20px; font-size: 0.76rem; color: var(--muted); }

  [data-panel] { display: none; }
  [data-panel].active { display: block; }

  /* ---------- bottom nav ---------- */
  nav.bottom-nav {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 10;
    background: rgba(19,58,62,0.98); backdrop-filter: blur(14px);
    border-top: 1px solid rgba(217,244,90,0.16);
    box-shadow: 0 -12px 28px rgba(3,12,14,0.5);
    display: flex; gap: 4px; padding: 8px 8px calc(8px + env(safe-area-inset-bottom));
  }
  .nav-btn {
    flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px;
    background: none; border: none; color: #fff; font-family: var(--sans);
    font-size: 0.67rem; font-weight: 600; padding: 7px 2px; cursor: pointer; border-radius: 12px;
  }
  .nav-btn svg { width: 22px; height: 22px; stroke-width: 2.2; }
  .nav-btn.active { color: var(--ball); background: rgba(217,244,90,0.12); }

  @media (min-width: 640px) {
    .wrap { padding: 0 24px; }
    nav.bottom-nav { position: static; background: none; backdrop-filter: none; border: none; padding: 0; margin-bottom: 18px; justify-content: flex-start; gap: 4px; }
    .nav-btn { flex: none; flex-direction: row; padding: 9px 14px; font-size: 0.82rem; border: 1px solid transparent; }
    .nav-btn.active { background: var(--surface); border-color: var(--line); }
    body { padding-bottom: 0; }
  }

  button:focus-visible, .rail-tick:focus-visible, input:focus-visible {
    outline: 2px solid var(--ball); outline-offset: 2px;
  }

  @media (prefers-reduced-motion: reduce) {
    .badge.live, .rail-tick.has-live { animation: none; }
    html { scroll-behavior: auto; }
  }
</style>
</head>
<body>
<div class="wrap">

  <header class="top">
    <div class="brand">{{ $tName }}</div>
    <a class="home-link" href="{{ route('home') }}">Pradinis ↗</a>
  </header>

  <p class="eyebrow">Grafikas ir rezultatai</p>
  <h1>Grafikas</h1>
  <p class="lede">
    @if($tDate)Turnyras {{ \Illuminate\Support\Carbon::parse($tDate)->locale('lt')->translatedFormat('F d') }} d. @endif
    Susirask save paieškoje arba atsidaryk viso klubo dieną. Sužaisti mačai rodo rezultatą iš karto.
  </p>

  <div class="day-rail-wrap" id="day-rail-wrap"></div>

  <div class="stat-strip">
    <span><b>{{ $stats['courts'] }}</b> kortai</span><span class="sep">·</span>
    <span><b>{{ $stats['clubs'] }}</b> klubai</span><span class="sep">·</span>
    <span><b>{{ $stats['matches'] }}</b> mačai</span><span class="sep">·</span>
    <span><b>{{ $stats['played'] }}</b> sužaista</span>
  </div>

  <div class="synced" id="synced-line">
    <span class="live-dot"></span>
    <span id="synced-text">@if($syncedAt) Atnaujinta {{ \Illuminate\Support\Carbon::parse($syncedAt)->timezone('Europe/Vilnius')->format('H:i') }} @else Duomenys dar nesinchronizuoti @endif</span>
  </div>

  <nav class="bottom-nav">
    <button class="nav-btn active" data-tab="paieska">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      Paieška
    </button>
    <button class="nav-btn" data-tab="tinklelis">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>
      Tinklelis
    </button>
    <button class="nav-btn" data-tab="klubai">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Klubai
    </button>
    <button class="nav-btn" data-tab="lentelės">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4a2 2 0 0 0 2 4M17 6h3a2 2 0 0 1-2 4"/></svg>
      Lentelės
    </button>
  </nav>

  {{-- Paieška --}}
  <div data-panel="paieska" class="active">
    <div class="search-box">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input type="text" id="search-input" placeholder="Ieškok žaidėjo ar klubo vardo…" autocomplete="off">
    </div>
    <div id="search-results"></div>
  </div>

  {{-- Tinklelis --}}
  <div data-panel="tinklelis">
    <div class="pills" id="division-pills">
      <button class="pill active" data-division="">Visi lygiai</button>
      @foreach($divisionList as $d)
        <button class="pill" data-division="{{ $d }}">{{ $d }}</button>
      @endforeach
    </div>
    <div id="grid-results"></div>
  </div>

  {{-- Klubai --}}
  <div data-panel="klubai">
    <div class="club-chips" id="club-chips"></div>
    <div id="club-results"></div>
  </div>

  {{-- Grupių lentelės --}}
  <div data-panel="lentelės">
    <div id="standings-results"></div>
  </div>

  <footer>
    Grafikas gyvai iš Tournated · © {{ date('Y') }} {{ $tName }}
  </footer>
</div>

<script>
(function () {
  var TOURNAMENT_ID = @json($tournamentId);
  var DATA_URL = @json(route('schedule.data', $tournamentId));
  var TOURNAMENT_DATE = @json($tDate);
  var CLUB_LIST = @json($clubList->values());
  var state = { matches: @json(array_values($matches)), groups: @json(array_values($groups)), syncedAt: @json($syncedAt) };

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
    if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase();
    return name.slice(0, 2).toUpperCase();
  }

  // Tournated sends a real club logo (team.image) on most matches — prefer
  // it, falling back to the colour-coded initials badge when a club has no
  // logo yet or the image fails to load.
  var clubImages = {};
  function noteClubImage(team) {
    if (team && team.title && team.image && !clubImages[team.title]) clubImages[team.title] = team.image;
  }
  function indexClubImages(matches) {
    matches.forEach(function (m) { noteClubImage(m.team1); noteClubImage(m.team2); });
  }
  function clubBadge(title, size) {
    size = size || 30;
    var img = clubImages[title];
    var html = '<span class="chip" style="background:' + clubColor(title) + ';width:' + size + 'px;height:' + size + 'px;font-size:' + Math.round(size * 0.4) + 'px">' + esc(initials(title));
    if (img) html += '<img src="' + esc(img) + '" alt="" loading="lazy" onerror="this.remove()">';
    return html + '</span>';
  }

  function esc(s) { return (s ?? '').toString().replace(/[&<>"]/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }

  function pairNames(participants, side) {
    return (participants || []).filter(function (p) { return p.side === side; })
      .map(function (p) { return ((p.name || '') + ' ' + (p.surname || '')).trim(); })
      .filter(Boolean);
  }
  // Plain-text form for search matching.
  function pairLabel(participants, side) {
    var names = pairNames(participants, side);
    return names.length ? names.join(' / ') : 'sudėtis nepaskelbta';
  }
  // Each player on their own line for the match card.
  function pairHtml(participants, side) {
    var names = pairNames(participants, side);
    return names.length ? names.map(esc).join('<br>') : 'sudėtis nepaskelbta';
  }

  function isPlayed(m) {
    return (m.sets && m.sets.length) || m.winner_side || m.is_walkover || m.is_bye;
  }

  function scoreText(m) {
    if (m.is_walkover) return 'W.O.';
    if (m.is_bye) return 'BYE';
    if (!m.sets || !m.sets.length) return '';
    return m.sets.map(function (s) {
      return typeof s === 'string' ? s : ((s.side1 ?? s[0] ?? '') + '-' + (s.side2 ?? s[1] ?? ''));
    }).join(', ');
  }

  // Tennis/padel-style scoreboard: each side's OWN number per set, in its own
  // row, so you read "6 6" across from BTC and "4 3" across from Pasitaškom —
  // not a single shared "6-4, 6-3" string you have to decode.
  function setNumbers(sets, side) {
    return (sets || []).map(function (s) {
      var a, b;
      if (typeof s === 'string') { var p = s.split('-'); a = parseInt(p[0], 10); b = parseInt(p[1], 10); }
      else { a = s.side1 ?? 0; b = s.side2 ?? 0; }
      var mine = side === 1 ? a : b, theirs = side === 1 ? b : a;
      if (isNaN(mine) || isNaN(theirs)) return '';
      return '<span class="set-cell' + (mine > theirs ? ' won' : '') + '">' + mine + '</span>';
    }).join('');
  }

  function matchRow(clubTitle, playersHtml, won, setsHtml) {
    return '' +
      '<div class="match-row' + (won ? ' winner' : '') + '">' +
        clubBadge(clubTitle, 30) +
        '<div class="row-text"><b>' + esc(clubTitle || 'Klubas') + '</b><span>' + playersHtml + '</span></div>' +
        (setsHtml ? '<div class="set-scores">' + setsHtml + '</div>' : '') +
      '</div>';
  }

  function matchCard(m) {
    var court = (m.court && m.court.name) || '—';
    var p1 = pairHtml(m.participants, 1), p2 = pairHtml(m.participants, 2);
    var played = isPlayed(m);
    var w = m.winner_side;
    var t1 = (m.team1 && m.team1.title) || '';
    var t2 = (m.team2 && m.team2.title) || '';
    var hasSets = played && m.sets && m.sets.length && !m.is_walkover && !m.is_bye;

    var badges = '';
    if (m.is_match_in_progress) badges += '<span class="badge live">● Vyksta</span>';
    if (m.division) badges += '<span class="badge division">' + esc(m.division) + '</span>';
    if (played && (m.is_walkover || m.is_bye)) badges += '<span class="badge score">' + esc(scoreText(m)) + '</span>';

    return '' +
      '<div class="match">' +
        '<div class="match-top"><span>' + esc(court) + '</span><span class="spacer"></span>' + badges + '</div>' +
        matchRow(t1, p1, played && w === 1, hasSets ? setNumbers(m.sets, 1) : '') +
        matchRow(t2, p2, played && w === 2, hasSets ? setNumbers(m.sets, 2) : '') +
      '</div>';
  }

  function timeOf(m) { return m.time || '—'; }

  function renderGrid(container, matches) {
    if (!matches.length) { container.innerHTML = '<p class="empty">Mačų nerasta.</p>'; return; }
    var byTime = {};
    matches.forEach(function (m) { (byTime[timeOf(m)] = byTime[timeOf(m)] || []).push(m); });
    var times = Object.keys(byTime).sort();
    var html = '';
    times.forEach(function (t) {
      html += '<div class="time-head" data-time="' + esc(t) + '">' + esc(t) + ' <span class="n">· ' + byTime[t].length + ' mačai</span></div>';
      byTime[t].forEach(function (m) { html += matchCard(m); });
    });
    container.innerHTML = html;
  }

  var activeDivision = '';
  function renderTinklelis() {
    var list = state.matches.filter(function (m) { return !activeDivision || m.division === activeDivision; });
    renderGrid(document.getElementById('grid-results'), list);
  }

  var activeClub = null;
  function renderClubChips() {
    var box = document.getElementById('club-chips');
    if (activeClub === null || CLUB_LIST.indexOf(activeClub) === -1) activeClub = CLUB_LIST[0] || null;
    box.innerHTML = CLUB_LIST.map(function (c) {
      var active = c === activeClub;
      return '<button class="club-chip' + (active ? ' active' : '') + '" data-club="' + esc(c) + '"' +
        (active ? ' style="--chip-c:' + clubColor(c) + '"' : '') + '>' + clubBadge(c, 22) + esc(c) + '</button>';
    }).join('');
  }
  function renderKlubai() {
    if (!activeClub) { document.getElementById('club-results').innerHTML = '<p class="empty">Pasirink klubą.</p>'; return; }
    var list = state.matches.filter(function (m) {
      return (m.team1 && m.team1.title === activeClub) || (m.team2 && m.team2.title === activeClub);
    });
    renderGrid(document.getElementById('club-results'), list);
  }

  function renderSearch(query) {
    var box = document.getElementById('search-results');
    if (!query) { box.innerHTML = '<p class="empty">Įvesk žaidėjo arba klubo vardą.</p>'; return; }
    var q = query.toLowerCase();
    var list = state.matches.filter(function (m) {
      var hay = [pairLabel(m.participants, 1), pairLabel(m.participants, 2), (m.team1 || {}).title, (m.team2 || {}).title, m.division]
        .join(' ').toLowerCase();
      return hay.indexOf(q) !== -1;
    });
    renderGrid(box, list);
  }

  function tallyMatches(matches) {
    var g = {};
    matches.forEach(function (m) {
      [1, 2].forEach(function (side) {
        var team = side === 1 ? m.team1 : m.team2;
        if (!team || !team.title) return;
        g[team.title] = g[team.title] || { played: 0, wins: 0, losses: 0, setsWon: 0, setsLost: 0 };
      });
      if (!isPlayed(m) || !m.team1 || !m.team2) return;
      var t1 = g[m.team1.title], t2 = g[m.team2.title];
      if (!t1 || !t2) return;
      t1.played++; t2.played++;
      if (m.winner_side === 1) { t1.wins++; t2.losses++; }
      else if (m.winner_side === 2) { t2.wins++; t1.losses++; }
      (m.sets || []).forEach(function (s) {
        var a = typeof s === 'string' ? parseInt(s.split('-')[0], 10) : (s.side1 ?? 0);
        var b = typeof s === 'string' ? parseInt(s.split('-')[1], 10) : (s.side2 ?? 0);
        if (isNaN(a) || isNaN(b)) return;
        t1.setsWon += a > b ? 1 : 0; t1.setsLost += a < b ? 1 : 0;
        t2.setsWon += b > a ? 1 : 0; t2.setsLost += b < a ? 1 : 0;
      });
    });
    return g;
  }

  function standingsTableHtml(title, tally) {
    var rows = Object.keys(tally).map(function (club) { return Object.assign({ club: club }, tally[club]); });
    if (!rows.length) return '';
    rows.sort(function (a, b) { return b.wins - a.wins || (b.setsWon - b.setsLost) - (a.setsWon - a.setsLost); });
    var html = '<div class="division-block"><h3 class="division-title">' + esc(title) + '</h3><div class="standings-list">';
    rows.forEach(function (r, i) {
      var diff = r.setsWon - r.setsLost;
      html += '' +
        '<div class="standing-row">' +
          '<span class="standing-rank' + (i === 0 ? ' top' : '') + '">' + (i + 1) + '</span>' +
          clubBadge(r.club, 26) +
          '<span class="standing-club">' + esc(r.club) + '</span>' +
          '<span class="standing-stats">' +
            '<span>' + r.played + '<em>ž.</em></span>' +
            '<span>' + r.wins + '-' + r.losses + '<em>w-l</em></span>' +
            '<span class="standing-diff' + (diff > 0 ? ' pos' : '') + '">' + (diff > 0 ? '+' : '') + diff + '<em>setai</em></span>' +
          '</span>' +
        '</div>';
    });
    return html + '</div></div>';
  }

  function renderStandings() {
    var box = document.getElementById('standings-results');
    if (!state.matches.length) { box.innerHTML = '<p class="empty">Lentelės pasirodys, kai bus paskelbti mačai.</p>'; return; }

    var overallTitle = (state.groups && state.groups[0] && state.groups[0].name) || 'Bendra';
    var html = standingsTableHtml(overallTitle, tallyMatches(state.matches));

    var byDivision = {};
    state.matches.forEach(function (m) {
      if (!m.division) return;
      (byDivision[m.division] = byDivision[m.division] || []).push(m);
    });
    Object.keys(byDivision).sort().forEach(function (div) {
      html += standingsTableHtml(div, tallyMatches(byDivision[div]));
    });
    box.innerHTML = html || '<p class="empty">Lentelės pasirodys, kai bus paskelbti mačai.</p>';
  }

  function renderDayRail() {
    var wrap = document.getElementById('day-rail-wrap');
    var times = Array.from(new Set(state.matches.map(function (m) { return m.time; }).filter(Boolean))).sort();
    if (!times.length) { wrap.innerHTML = ''; return; }
    function toMin(t) { var p = t.split(':'); return (+p[0]) * 60 + (+p[1]); }
    var min = toMin(times[0]), max = toMin(times[times.length - 1]);
    var span = Math.max(max - min, 1);
    var byTime = {};
    state.matches.forEach(function (m) { (byTime[m.time] = byTime[m.time] || []).push(m); });

    var ticks = times.map(function (t) {
      var pct = ((toMin(t) - min) / span) * 100;
      var ms = byTime[t] || [];
      var live = ms.some(function (m) { return m.is_match_in_progress; });
      var played = ms.some(isPlayed);
      var cls = live ? ' has-live' : (played ? ' has-played' : '');
      return '<span class="rail-tick' + cls + '" style="left:' + pct + '%" data-time="' + esc(t) + '" title="' + esc(t) + '"></span>';
    }).join('');

    var nowHtml = '';
    if (TOURNAMENT_DATE) {
      var now = new Date();
      var pad = function (n) { return String(n).padStart(2, '0'); };
      var todayStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
      if (todayStr === TOURNAMENT_DATE) {
        var nowMin = now.getHours() * 60 + now.getMinutes();
        if (nowMin >= min && nowMin <= max) {
          nowHtml = '<span class="rail-now" style="left:' + (((nowMin - min) / span) * 100) + '%"></span>';
        }
      }
    }

    wrap.innerHTML = '<div class="day-rail"><span class="rail-label">' + esc(times[0]) + '</span>' +
      '<div class="rail-track">' + ticks + nowHtml + '</div>' +
      '<span class="rail-label">' + esc(times[times.length - 1]) + '</span></div>';
  }

  function renderAll() {
    indexClubImages(state.matches);
    renderDayRail(); renderClubChips(); renderTinklelis(); renderKlubai(); renderStandings();
    var q = document.getElementById('search-input').value.trim();
    renderSearch(q);
  }

  function switchTab(tab) {
    document.querySelectorAll('.nav-btn').forEach(function (b) { b.classList.toggle('active', b.dataset.tab === tab); });
    document.querySelectorAll('[data-panel]').forEach(function (p) { p.classList.toggle('active', p.dataset.panel === tab); });
  }

  document.querySelectorAll('.nav-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { switchTab(btn.dataset.tab); });
  });

  document.getElementById('division-pills').addEventListener('click', function (e) {
    var btn = e.target.closest('.pill');
    if (!btn) return;
    document.querySelectorAll('#division-pills .pill').forEach(function (p) { p.classList.remove('active'); });
    btn.classList.add('active');
    activeDivision = btn.dataset.division;
    renderTinklelis();
  });

  document.getElementById('club-chips').addEventListener('click', function (e) {
    var btn = e.target.closest('.club-chip');
    if (!btn) return;
    activeClub = btn.dataset.club;
    renderClubChips();
    renderKlubai();
  });

  document.getElementById('search-input').addEventListener('input', function (e) {
    renderSearch(e.target.value.trim());
  });

  document.getElementById('day-rail-wrap').addEventListener('click', function (e) {
    var tick = e.target.closest('.rail-tick');
    if (!tick) return;
    switchTab('tinklelis');
    document.querySelectorAll('#division-pills .pill').forEach(function (p) { p.classList.remove('active'); });
    document.querySelector('#division-pills .pill[data-division=""]').classList.add('active');
    activeDivision = '';
    renderTinklelis();
    requestAnimationFrame(function () {
      var head = document.querySelector('.time-head[data-time="' + tick.dataset.time + '"]');
      if (head) head.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  function fmtSyncTime(iso) {
    if (!iso) return null;
    try {
      return new Date(iso).toLocaleTimeString('lt-LT', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Europe/Vilnius' });
    } catch (e) { return null; }
  }

  // Rodo tikrą paskutinio sinchronizavimo laiką — ne "ką tik", nes tai
  // klaidina, kai relay scenarijus nebeveikia ir duomenys realiai nesikeičia.
  function refresh() {
    fetch(DATA_URL).then(function (r) { return r.json(); }).then(function (json) {
      state.matches = json.matches || [];
      state.groups = json.groups || state.groups;
      state.syncedAt = json.synced_at;
      renderAll();
      var t = document.getElementById('synced-text');
      var time = fmtSyncTime(state.syncedAt);
      t.textContent = time ? 'Atnaujinta ' + time : 'Duomenys dar nesinchronizuoti';
    }).catch(function () {});
  }

  renderAll();
  setInterval(refresh, 45000);
})();
</script>
</body>
</html>
