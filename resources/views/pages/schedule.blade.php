@php
    $tName = $tournament['name'] ?? 'Turnyras';
    $tDate = $tournament['date'] ?? null;
    $hasClubs = $clubList->isNotEmpty();
@endphp
<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Grafikas · {{ $tName }}</title>
<meta name="theme-color" content="#0A2226">
<link rel="manifest" href="{{ route('push.manifest', $tournamentId) }}">
<link rel="apple-touch-icon" href="{{ route('push.icon', 192) }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Grafikas">
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
  .brand { font-family: var(--display); font-weight: 700; font-size: 0.9rem; line-height: 1.25; min-width: 0; }
  .brand small { display: block; font-family: var(--sans); font-weight: 500; font-size: 0.74rem; color: var(--muted); margin-top: 2px; }
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
  .notify-note { font-size: 0.78rem; color: var(--muted); line-height: 1.45; margin-top: 8px; }

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
    font-family: var(--display); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.02em;
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
  .row-text .me-name { color: var(--ball); font-weight: 600; }
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
  .crow .k { font-family: var(--display); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); font-weight: 700; padding-top: 3px; }
  .crow .k.live { color: var(--ball); }
  .crow .v { font-size: 0.88rem; line-height: 1.45; min-width: 0; }
  .crow .v .tt { font-family: var(--display); font-weight: 700; font-size: 1.02rem; font-variant-numeric: tabular-nums; margin-right: 6px; }
  .crow .v .sub { color: var(--muted); }
  .crow .over { display: block; font-size: 0.78rem; color: var(--warn); margin-top: 2px; }
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
  .standing-stats em { font-style: normal; color: var(--muted); font-size: 0.64rem; display: block; text-align: center; }
  .standing-diff { min-width: 34px; text-align: right; }
  .standing-diff.pos { color: var(--ball); }

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
    background: none; border: none; color: var(--ink-soft); font-size: 0.68rem; font-weight: 600;
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
<div class="wrap">

  <header class="top">
    <div class="brand">{{ $tName }}<small id="tour-date"></small></div>
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
    <button class="nav-btn" data-tab="lenteles">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4a2 2 0 0 0 2 4M17 6h3a2 2 0 0 1-2 4"/></svg>
      Lentelės
    </button>
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

  {{-- Lentelės --}}
  <div data-panel="lenteles">
    <div id="standings-results"></div>
  </div>

  <footer>
    <span>Grafikas gyvai iš Tournated · {{ $tName }}</span>
    <a href="{{ route('home') }}">padelioturnyrai.lt</a>
  </footer>
</div>

<script>
// Phone notifications ("Tavo eilė! Eik į Kortą 4"): one subscription per
// phone, tied to the player chosen on the "Mano" tab.
window.PushUI = (function () {
  var VAPID = @json(config('services.push.vapid_public'));
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
    if (!slot || !VAPID) return;
    if (!supported) {
      slot.innerHTML = isIOS && !standalone
        ? '<p class="notify-note">🔔 Norėdamas gauti pranešimą, kai tavo eilė: „Safari“ spausk <b>Bendrinti</b> → <b>Į pradžios ekraną</b>, tada atsidaryk grafiką iš ten.</p>'
        : '';
      return;
    }
    if (Notification.permission === 'denied') {
      slot.innerHTML = '<p class="notify-note">🔕 Pranešimai šiai svetainei užblokuoti telefono nustatymuose.</p>';
      return;
    }
    var on = get() === player && Notification.permission === 'granted';
    slot.innerHTML = '<button class="notify-btn' + (on ? ' on' : '') + '" id="notify-btn"' + (busy ? ' disabled' : '') + '>' +
      (busy ? 'Palauk…' : (on ? '🔔 Pranešimai įjungti · išjungti' : '🔔 Pranešti man, kai mano eilė')) + '</button>' +
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
  var TZ = 'Europe/Vilnius';
  var STORE_KEY = 'grafikas:' + TOURNAMENT_ID + ':player';
  var state = {
    matches: @json(array_values($matches)),
    groups: @json(array_values($groups)),
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
    if (m.is_match_in_progress) badges += '<span class="badge live">● Vyksta</span>';
    if (m.division) badges += '<span class="badge division">' + esc(m.division) + '</span>';
    if (m.is_walkover) badges += '<span class="badge">Be žaidimo (W.O.)</span>';
    var cls = 'match' + (me && sideOf(m, me) ? ' mine' : '') + (isScheduled(m) ? '' : ' unsched');
    return '<div class="' + cls + '">' +
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
    renderMano(); renderGrid(); renderKlubai();
    window.scrollTo(0, 0);
  }
  function forgetPlayer() {
    me = null; store(false, null);
    try { var u = new URL(location.href); u.searchParams.delete('z'); history.replaceState(null, '', u); } catch (e) {}
    renderMano(); renderGrid(); renderKlubai();
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
    if (!before.length) return 'Tu – kitas korte ' + court.replace('Kortas ', '') + '.';
    if (before.length === 1 && m.date === now.date && startMin(before[0]) <= now.min) {
      return 'Korte ' + court.replace('Kortas ', '') + ' dabar žaidžia ' + esc(sideTitle(before[0], 1)) + ' – ' + esc(sideTitle(before[0], 2)) +
        ' (nuo ' + esc(before[0].eta || before[0].time) + '), tu – po jų.';
    }
    return 'Prieš tave korte ' + court.replace('Kortas ', '') + ' dar ' + plural(before.length, 'mačas', 'mačai', 'mačų') + '.';
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
        (played.length ? 'Visi tavo mačai sužaisti 🎾' : 'Mačų kol kas nėra') + '</div></div>';
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
          ' <span class="sub">· ' + (running.division ? esc(running.division) + ' · ' : '') + 'nuo ' + esc(running.eta || running.time) + '</span>' +
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
          (next.eta && next.eta !== next.time ? ' · planuota ' + esc(next.time) + (toMin(next.eta) < toMin(next.time) ? ', gali anksčiau' : '') : '') + '</span></span></div>';
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
    document.getElementById('club-results').innerHTML = gridHtml(list, me) || '<p class="empty">Mačų nerasta.</p>';
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
        if (team && team.title) g[team.title] = g[team.title] || { played: 0, wins: 0, losses: 0, setsWon: 0, setsLost: 0 };
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
      });
    });
    return g;
  }
  // Tournated's club table scores by TIE (the whole club-vs-club meeting):
  // the club that won more individual matches against an opponent gets 1 point.
  function tiePointsByClub(matches) {
    var pairs = {};
    matches.forEach(function (m) {
      if (!isPlayed(m) || !m.team1 || !m.team2 || !m.winner_side) return;
      var a = m.team1.title, b = m.team2.title, key = [a, b].sort().join('||');
      pairs[key] = pairs[key] || {}; pairs[key][a] = pairs[key][a] || 0; pairs[key][b] = pairs[key][b] || 0;
      if (m.winner_side === 1) pairs[key][a]++; else pairs[key][b]++;
    });
    var points = {};
    Object.keys(pairs).forEach(function (key) {
      var clubs = key.split('||'), wa = pairs[key][clubs[0]] || 0, wb = pairs[key][clubs[1]] || 0;
      if (wa > wb) points[clubs[0]] = (points[clubs[0]] || 0) + 1;
      else if (wb > wa) points[clubs[1]] = (points[clubs[1]] || 0) + 1;
    });
    return points;
  }
  function standingsTableHtml(title, tally, tiePoints) {
    var rows = Object.keys(tally).map(function (club) { return Object.assign({ club: club, tiePoints: (tiePoints && tiePoints[club]) || 0 }, tally[club]); });
    if (!rows.length) return '';
    rows.sort(tiePoints
      ? function (a, b) { return b.tiePoints - a.tiePoints || (b.setsWon - b.setsLost) - (a.setsWon - a.setsLost); }
      : function (a, b) { return b.wins - a.wins || (b.setsWon - b.setsLost) - (a.setsWon - a.setsLost); });
    var html = '<div class="division-block"><h3 class="division-title">' + esc(title) + '</h3><div class="standings-list">';
    rows.forEach(function (r, i) {
      var diff = r.setsWon - r.setsLost;
      html += '<div class="standing-row"><span class="standing-rank' + (i === 0 ? ' top' : '') + '">' + (i + 1) + '</span>' +
        clubBadge(r.club, 26) + '<span class="standing-club">' + esc(r.club) + '</span><span class="standing-stats">' +
        (tiePoints ? '<span>' + r.tiePoints + '<em>tšk.</em></span>' : '') +
        '<span>' + r.wins + '–' + r.losses + '<em>laim.–pral.</em></span>' +
        '<span class="standing-diff' + (diff > 0 ? ' pos' : '') + '">' + (diff > 0 ? '+' : '') + diff + '<em>setai</em></span></span></div>';
    });
    return html + '</div></div>';
  }
  function renderStandings() {
    var box = document.getElementById('standings-results');
    if (!HAS_CLUBS) { box.innerHTML = '<p class="empty">Šiam turnyrui lentelės dar ruošiamos.</p>'; return; }
    if (!state.matches.length) { box.innerHTML = '<p class="empty">Lentelės pasirodys, kai bus paskelbti mačai.</p>'; return; }
    var overall = (state.groups && state.groups[0] && state.groups[0].name) || 'Bendra';
    var html = standingsTableHtml(overall, tallyMatches(state.matches), tiePointsByClub(state.matches));
    var byDivision = {};
    state.matches.forEach(function (m) { if (m.division) (byDivision[divisionGroup(m.division)] = byDivision[divisionGroup(m.division)] || []).push(m); });
    Object.keys(byDivision).sort().forEach(function (d) { html += standingsTableHtml(d, tallyMatches(byDivision[d])); });
    box.innerHTML = html;
  }

  /* ---------- header, tabs, refresh ---------- */
  function renderHeader() {
    if (TOURNAMENT_DATE) document.getElementById('tour-date').textContent = dayLabel(TOURNAMENT_DATE);
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
    indexClubImages(state.matches);
    renderHeader(); renderMano(); renderDabar(); renderGrid(); renderKlubai(); renderStandings();
  }

  function switchTab(tab) {
    document.querySelectorAll('.nav-btn').forEach(function (b) {
      var on = b.dataset.tab === tab;
      b.classList.toggle('active', on);
      if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
    });
    document.querySelectorAll('[data-panel]').forEach(function (p) { p.classList.toggle('active', p.dataset.panel === tab); });
    window.scrollTo(0, 0);
  }
  document.querySelectorAll('.nav-btn').forEach(function (btn) { btn.addEventListener('click', function () { switchTab(btn.dataset.tab); }); });

  // Re-render only when the data really changed (no flicker every 45 s);
  // refresh at once when the phone comes back to the page.
  var lastBody = null;
  function refresh() {
    fetch(DATA_URL, { cache: 'no-store' }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); }).then(function (body) {
      state.lastFetchOk = true;
      if (body === lastBody) { renderHeader(); return; }
      lastBody = body;
      var json = JSON.parse(body);
      state.matches = json.matches || [];
      state.groups = json.groups || state.groups;
      state.syncedAt = json.synced_at;
      var typing = document.activeElement && document.activeElement.id === 'me-search';
      if (typing) { renderHeader(); renderDabar(); renderGrid(); renderKlubai(); renderStandings(); } else renderAll();
    }).catch(function () { state.lastFetchOk = false; renderHeader(); });
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') refresh(); });
  setInterval(function () { if (document.visibilityState === 'visible') refresh(); }, 45000);
  setInterval(function () { renderHeader(); renderDabar(); }, 30000);

  renderAll();
})();
</script>
</body>
</html>
