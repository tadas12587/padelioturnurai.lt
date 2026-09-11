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
<style>
  :root {
    --bg: #12131c; --panel: #191b28; --panel2: #1f2233; --line: #2b2f45;
    --ink: #f1f1f6; --ink-soft: #b7bad0; --muted: #7d8099;
    --accent: #9184d9; --accent-ink: #efeaff;
    --live: #e0577a; --go: #4fc48a; --go-wash: #17301f;
    --sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--ink); font-family: var(--sans); -webkit-font-smoothing: antialiased; }
  a { color: inherit; }
  .wrap { max-width: 980px; margin: 0 auto; padding: 0 18px 80px; }

  header.top { padding: 22px 0 10px; display: flex; justify-content: space-between; align-items: center; }
  .brand { font-weight: 700; letter-spacing: 0.02em; font-size: 0.95rem; text-transform: uppercase; }
  .brand b { color: var(--accent-ink); }
  .home-link { font-size: 0.82rem; color: var(--muted); text-decoration: none; }
  .home-link:hover { color: var(--ink); }

  .eyebrow { font-size: 0.74rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent); margin: 18px 0 6px; }
  h1 { font-size: clamp(2rem, 6vw, 2.8rem); margin: 0 0 10px; letter-spacing: -0.02em; }
  .lede { color: var(--ink-soft); max-width: 62ch; line-height: 1.5; margin: 0 0 20px; }

  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: 10px; margin-bottom: 22px; }
  .stat { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 14px 12px; text-align: center; }
  .stat b { display: block; font-size: 1.5rem; line-height: 1.1; }
  .stat span { font-size: 0.66rem; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }

  .synced { font-size: 0.74rem; color: var(--muted); margin-bottom: 18px; display: flex; align-items: center; gap: 6px; }
  .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--go); display: inline-block; }

  .tabs { display: flex; gap: 6px; border-bottom: 1px solid var(--line); margin-bottom: 18px; overflow-x: auto; }
  .tab-btn { font: inherit; font-size: 0.86rem; font-weight: 600; color: var(--muted); background: none; border: none; padding: 10px 14px; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; }
  .tab-btn.active { color: var(--ink); border-color: var(--accent); }

  .pills { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .pill { font-size: 0.78rem; font-weight: 600; color: var(--ink-soft); background: var(--panel); border: 1px solid var(--line); border-radius: 999px; padding: 7px 13px; cursor: pointer; }
  .pill.active { color: var(--accent-ink); background: var(--accent); border-color: var(--accent); }

  .search-box { position: relative; margin-bottom: 18px; }
  .search-box input { width: 100%; font: inherit; font-size: 0.95rem; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px 12px 38px; color: var(--ink); }
  .search-box input:focus { outline: 2px solid var(--accent); outline-offset: -1px; }
  .search-box::before { content: "⌕"; position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1.1rem; }

  .time-head { font-family: var(--sans); font-weight: 700; font-size: 1.15rem; margin: 22px 0 8px; display: flex; align-items: baseline; gap: 10px; }
  .time-head span { font-size: 0.72rem; font-weight: 600; color: var(--muted); letter-spacing: 0.04em; }

  .card { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; margin-bottom: 8px; display: grid; grid-template-columns: 46px 1fr auto; gap: 14px; align-items: center; }
  .card .court { text-align: center; font-family: var(--sans); }
  .card .court b { display: block; font-size: 1.2rem; line-height: 1; }
  .card .court span { font-size: 0.6rem; color: var(--muted); letter-spacing: 0.06em; text-transform: uppercase; }
  .card .who b { display: block; font-size: 0.98rem; margin-bottom: 3px; }
  .card .who .clubs { font-size: 0.76rem; color: var(--muted); margin-bottom: 4px; }
  .card .side { display: flex; align-items: center; gap: 6px; font-size: 0.94rem; }
  .card .side .vs { color: var(--muted); font-size: 0.8rem; }
  .card .side.winner { color: var(--go); font-weight: 700; }
  .badges { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
  .badge { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em; padding: 3px 8px; border-radius: 999px; background: var(--panel2); border: 1px solid var(--line); color: var(--ink-soft); white-space: nowrap; }
  .badge.live { background: var(--live); border-color: var(--live); color: #fff; animation: pulse 1.6s ease-in-out infinite; }
  .badge.score { background: var(--go-wash); border-color: var(--go); color: var(--go); font-variant-numeric: tabular-nums; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.55; } }

  .club-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .club-chip { font-size: 0.82rem; font-weight: 600; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 9px 14px; cursor: pointer; color: var(--ink-soft); }
  .club-chip.active { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); }

  .division-block { margin-bottom: 30px; }
  .division-title { font-size: 1.05rem; font-weight: 700; margin: 0 0 10px; }
  table.standings { width: 100%; border-collapse: collapse; font-size: 0.88rem; background: var(--panel); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
  table.standings th { text-align: left; font-size: 0.66rem; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); padding: 10px 12px; border-bottom: 1px solid var(--line); }
  table.standings td { padding: 10px 12px; border-bottom: 1px solid var(--line); color: var(--ink-soft); }
  table.standings tr:last-child td { border-bottom: none; }
  table.standings td:first-child, table.standings th:first-child { color: var(--ink); font-weight: 700; width: 34px; }
  table.standings td.club { color: var(--ink); font-weight: 600; }
  table.standings td.num { text-align: center; font-variant-numeric: tabular-nums; }

  .empty { color: var(--muted); font-size: 0.9rem; padding: 30px 0; text-align: center; }

  footer { border-top: 1px solid var(--line); margin-top: 50px; padding-top: 18px; font-size: 0.78rem; color: var(--muted); }

  [data-panel] { display: none; }
  [data-panel].active { display: block; }
</style>
</head>
<body>
<div class="wrap">

  <header class="top">
    <div class="brand"><b>{{ $tName }}</b></div>
    <a class="home-link" href="{{ route('home') }}">Pradinis ↗</a>
  </header>

  <p class="eyebrow">Grafikas ir rezultatai</p>
  <h1>Grafikas</h1>
  <p class="lede">
    @if($tDate)Turnyras {{ \Illuminate\Support\Carbon::parse($tDate)->locale('lt')->translatedFormat('F d') }} d.@endif
    Susirask save paieškoje arba atsidaryk viso klubo dieną — kada, kuriame korte ir prieš ką žaidi. Sužaisti mačai rodo rezultatą iš karto.
  </p>

  <div class="stats">
    <div class="stat"><b>{{ $stats['courts'] }}</b><span>Kortai</span></div>
    <div class="stat"><b>{{ $stats['divisions'] }}</b><span>Lygiai</span></div>
    <div class="stat"><b>{{ $stats['clubs'] }}</b><span>Klubai</span></div>
    <div class="stat"><b>{{ $stats['matches'] }}</b><span>Mačai</span></div>
    <div class="stat"><b>{{ $stats['played'] }}</b><span>Sužaista</span></div>
  </div>

  <div class="synced" id="synced-line">
    <span class="dot"></span>
    <span id="synced-text">@if($syncedAt) Atnaujinta {{ \Illuminate\Support\Carbon::parse($syncedAt)->locale('lt')->diffForHumans() }} @else Duomenys dar nesinchronizuoti @endif</span>
  </div>

  <div class="tabs">
    <button class="tab-btn active" data-tab="paieska">Paieška</button>
    <button class="tab-btn" data-tab="tinklelis">Tinklelis</button>
    <button class="tab-btn" data-tab="klubai">Klubai</button>
    <button class="tab-btn" data-tab="lentelės">Grupių lentelės</button>
  </div>

  {{-- Paieška --}}
  <div data-panel="paieska" class="active">
    <div class="search-box">
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
    <div class="club-chips" id="club-chips">
      @foreach($clubList as $c)
        <button class="club-chip @if($loop->first) active @endif" data-club="{{ $c }}">{{ $c }}</button>
      @endforeach
    </div>
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
  var state = { matches: @json(array_values($matches)), syncedAt: @json($syncedAt) };

  function esc(s) { return (s ?? '').toString().replace(/[&<>"]/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }

  function pairLabel(participants, side) {
    var names = (participants || []).filter(function (p) { return p.side === side; })
      .map(function (p) { return (p.name || '') + ' ' + (p.surname || ''); }).map(function (s) { return s.trim(); });
    return names.length ? names.join(' / ') : 'sudėtis nepaskelbta';
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

  function matchCard(m) {
    var court = (m.court && m.court.name) ? m.court.name.replace(/[^0-9]/g, '') || m.court.name : '–';
    var p1 = pairLabel(m.participants, 1), p2 = pairLabel(m.participants, 2);
    var played = isPlayed(m);
    var w = m.winner_side;
    var side1Cls = played && w === 1 ? ' winner' : '';
    var side2Cls = played && w === 2 ? ' winner' : '';
    var clubsLine = (m.team1 && m.team1.title ? esc(m.team1.title) : '') + (m.team2 && m.team2.title ? ' – ' + esc(m.team2.title) : '');
    var badges = '';
    if (m.is_match_in_progress) badges += '<span class="badge live">VYKSTA</span>';
    else if (played) badges += '<span class="badge score">' + esc(scoreText(m)) + '</span>';
    if (m.division) badges += '<span class="badge">' + esc(m.division) + '</span>';

    return '' +
      '<div class="card">' +
        '<div class="court"><b>' + esc(court) + '</b><span>kortas</span></div>' +
        '<div class="who">' +
          (clubsLine ? '<div class="clubs">' + clubsLine + '</div>' : '') +
          '<div class="side' + side1Cls + '">' + esc(p1) + '</div>' +
          '<div class="side' + side2Cls + '"><span class="vs">vs</span> ' + esc(p2) + '</div>' +
        '</div>' +
        '<div class="badges">' + badges + '</div>' +
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
      html += '<div class="time-head">' + esc(t) + '</div>';
      byTime[t].forEach(function (m) { html += matchCard(m); });
    });
    container.innerHTML = html;
  }

  var activeDivision = '';
  function renderTinklelis() {
    var list = state.matches.filter(function (m) { return !activeDivision || m.division === activeDivision; });
    renderGrid(document.getElementById('grid-results'), list);
  }

  var activeClub = document.querySelector('#club-chips .club-chip.active');
  activeClub = activeClub ? activeClub.dataset.club : null;
  function renderKlubai() {
    if (!activeClub) { document.getElementById('club-results').innerHTML = '<p class="empty">Pasirink klubą.</p>'; return; }
    var list = state.matches.filter(function (m) {
      return (m.team1 && m.team1.title === activeClub) || (m.team2 && m.team2.title === activeClub);
    });
    renderGrid(document.getElementById('club-results'), list);
  }

  function renderSearch(query) {
    var box = document.getElementById('search-results');
    if (!query) { box.innerHTML = ''; return; }
    var q = query.toLowerCase();
    var list = state.matches.filter(function (m) {
      var hay = [pairLabel(m.participants, 1), pairLabel(m.participants, 2), (m.team1 || {}).title, (m.team2 || {}).title, m.division]
        .join(' ').toLowerCase();
      return hay.indexOf(q) !== -1;
    });
    renderGrid(box, list);
  }

  function renderStandings() {
    var box = document.getElementById('standings-results');
    var groups = {};
    state.matches.forEach(function (m) {
      if (!m.division) return;
      groups[m.division] = groups[m.division] || {};
      var g = groups[m.division];
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

    var divisions = Object.keys(groups).sort();
    if (!divisions.length) { box.innerHTML = '<p class="empty">Lentelės pasirodys, kai bus paskelbti lygiai.</p>'; return; }

    var html = '';
    divisions.forEach(function (div) {
      var rows = Object.keys(groups[div]).map(function (club) { return Object.assign({ club: club }, groups[div][club]); });
      rows.sort(function (a, b) { return b.wins - a.wins || (b.setsWon - b.setsLost) - (a.setsWon - a.setsLost); });
      html += '<div class="division-block"><h3 class="division-title">' + esc(div) + '</h3>' +
        '<table class="standings"><thead><tr><th>#</th><th>Klubas</th><th class="num">Žaista</th><th class="num">Laim.</th><th class="num">Pral.</th><th class="num">Setai</th></tr></thead><tbody>';
      rows.forEach(function (r, i) {
        html += '<tr><td>' + (i + 1) + '</td><td class="club">' + esc(r.club) + '</td><td class="num">' + r.played + '</td><td class="num">' + r.wins + '</td><td class="num">' + r.losses + '</td><td class="num">' + r.setsWon + '-' + r.setsLost + '</td></tr>';
      });
      html += '</tbody></table></div>';
    });
    box.innerHTML = html;
  }

  function renderAll() {
    renderTinklelis(); renderKlubai(); renderStandings();
    var q = document.getElementById('search-input').value.trim();
    if (q) renderSearch(q);
  }

  document.querySelectorAll('.tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
      document.querySelectorAll('[data-panel]').forEach(function (p) { p.classList.remove('active'); });
      btn.classList.add('active');
      document.querySelector('[data-panel="' + btn.dataset.tab + '"]').classList.add('active');
    });
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
    document.querySelectorAll('#club-chips .club-chip').forEach(function (c) { c.classList.remove('active'); });
    btn.classList.add('active');
    activeClub = btn.dataset.club;
    renderKlubai();
  });

  document.getElementById('search-input').addEventListener('input', function (e) {
    renderSearch(e.target.value.trim());
  });

  function refresh() {
    fetch(DATA_URL).then(function (r) { return r.json(); }).then(function (json) {
      state.matches = json.matches || [];
      state.syncedAt = json.synced_at;
      renderAll();
      var t = document.getElementById('synced-text');
      if (state.syncedAt) t.textContent = 'Atnaujinta ką tik';
    }).catch(function () {});
  }

  renderAll();
  setInterval(refresh, 45000);
})();
</script>
</body>
</html>
