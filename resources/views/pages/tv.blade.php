<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>TV · {{ $title }}</title>
<meta name="theme-color" content="#0A2226">
{{-- fonts must never hold the schedule back on a slow venue network: load them without blocking --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@600;700;800&display=swap" media="print" onload="this.media='all'">
{{--
  Written for old and cheap TV browsers too (Samsung Tizen / LG webOS from ~2016,
  Android TV): no CSS variables, no flex/grid gap, no min()/clamp(); plain ES5
  script (var, functions, XMLHttpRequest). Newer browsers get extras on top
  (slide animation, wake lock) where they are feature-detected.
--}}
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: #0A2226; color: #F2F7F5; overflow: hidden; }
body {
  /* set from the TV size and viewing distance (ADA 703.5.5), see fit() */
  font-size: 26px;
  font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif; font-weight: 700;
  display: -webkit-box; display: -webkit-flex; display: flex;
  -webkit-box-orient: vertical; -webkit-flex-direction: column; flex-direction: column;
  padding: .5em .6em .35em;
}
.top, .board, .foot { -webkit-flex: none; flex: none; }
.top { display: -webkit-flex; display: flex; -webkit-align-items: center; align-items: center; font-family: Archivo, Inter, Arial, sans-serif; font-size: .8em; margin-bottom: .45em; }
.top img { width: 1.7em; height: 1.7em; margin-right: .6em; }
/* logos come in any shape: keep the header height, let the width follow (up to ~4 squares) */
.top img.logo { width: auto; max-width: 9em; height: 3em; -o-object-fit: contain; object-fit: contain; border-radius: .2em; }
.top h1 { font-size: 1.15em; font-weight: 900; -webkit-flex: 1; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-right: .6em; }
.clock { font-size: 1.6em; font-weight: 900; color: #D9F45A; line-height: 1; text-align: right; }
.fresh { color: #9DB7B1; font-size: .7em; text-align: right; font-family: Inter, Arial, sans-serif; }
.fresh.bad { color: #F5B54A; }

.board { -webkit-flex: 1 1 auto; flex: 1 1 auto; min-height: 0; display: -webkit-flex; display: flex; overflow: hidden; }
.court { -webkit-flex: 1 1 0; flex: 1 1 0; width: 0; min-width: 0; min-height: 0; overflow: hidden; margin-right: .45em; border-top: .12em solid #EAF3F1; padding-top: .2em; }
.court:last-child { margin-right: 0; }
.court h2 {
  font-family: Archivo, Inter, Arial, sans-serif; font-weight: 900; font-size: 1.1em; text-transform: uppercase; letter-spacing: .03em;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  background: #EAF3F1; color: #0A2226; border-radius: .25em; padding: .05em .4em; margin-bottom: .3em;
}
/* every court its own colour (light, so dark text stays readable) — easier to find yours from afar */
.c1 { border-top-color: #EAF3F1; } .c1 h2 { background: #EAF3F1; }
.c2 { border-top-color: #8FD3FF; } .c2 h2 { background: #8FD3FF; }
.c3 { border-top-color: #FFB38A; } .c3 h2 { background: #FFB38A; }
.c4 { border-top-color: #C9B6FF; } .c4 h2 { background: #C9B6FF; }
.c5 { border-top-color: #86E3B5; } .c5 h2 { background: #86E3B5; }
.c6 { border-top-color: #FFD66B; } .c6 h2 { background: #FFD66B; }
.slot { border-radius: .3em; padding: .25em .4em .3em; margin-bottom: .3em; background: #0E2C30; border: .06em solid rgba(238,244,242,.14); }
.slot > * + * { margin-top: .12em; }
.hid { display: none !important; }
/* already played: smaller, people only glance at the score */
.slot.done { background: transparent; font-size: .72em; }
.slot.live { background: #14444A; border: .12em solid #D9F45A; }
.slot.call { background: #D9F45A; color: #17230A; border: .12em solid #D9F45A; }
.slot.empty { color: #9DB7B1; border-style: dashed; background: transparent; font-size: .8em; }
.lab { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: Archivo, Inter, Arial, sans-serif; font-weight: 900; text-transform: uppercase; }
.lab .t { font-size: 1.05em; }
.lab .t small { font-size: .62em; font-weight: 800; text-transform: none; color: #C3D8D3; }
.call .lab .t small { color: #33440f; }
.live .lab .t { color: #D9F45A; }
.lab .late { font-family: Inter, Arial, sans-serif; text-transform: none; color: #F5B54A; font-size: .6em; font-weight: 700; margin-left: .4em; }
.call .lab .late { color: #5b4300; }
.grp { font-size: .7em; font-weight: 800; color: #C3D8D3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.grp .st { font-family: Archivo, Inter, Arial, sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; color: #F2F7F5; }
.live .grp .st { color: #D9F45A; }
.call .grp, .call .grp .st { color: #17230A; }
.call .grp .g { color: #33440f; }
/* on the cards people act on, the group gets its own line instead of being cut */
.live .grp .g, .call .grp .g { display: block; overflow: hidden; text-overflow: ellipsis; }
.dot { display: inline-block; width: .55em; height: .55em; border-radius: 50%; background: #D9F45A; margin-right: .3em; -webkit-animation: pulse 1.6s ease-in-out infinite; animation: pulse 1.6s ease-in-out infinite; }
@-webkit-keyframes pulse { 50% { opacity: .35; } }
@keyframes pulse { 50% { opacity: .35; } }
.side { display: -webkit-flex; display: flex; -webkit-align-items: center; align-items: center; }
.side + .side { border-top: .05em solid rgba(238,244,242,.14); padding-top: .1em; }
.call .side + .side { border-color: rgba(23,35,10,.25); }
.who { -webkit-flex: 1; flex: 1; min-width: 0; }
.p { display: -webkit-flex; display: flex; -webkit-align-items: baseline; align-items: baseline; min-width: 0; line-height: 1.15; font-size: .9em; }
/* the name wins the space; the place takes what is left */
.p .n { font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; -webkit-flex: 0 1 auto; flex: 0 1 auto; max-width: 100%; }
.p .pl { color: #C3D8D3; font-size: .78em; font-weight: 700; white-space: nowrap; overflow: hidden; -webkit-flex: 1 1 0; flex: 1 1 0; min-width: 0; margin-left: .35em; }
.call .p .pl, .call .club { color: #33440f; }
.club { font-size: .72em; font-weight: 800; color: #D9F45A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.done .p .n { font-weight: 700; color: #C3D8D3; }
.done .p .pl { font-size: .85em; }
.done .win .n { color: #F2F7F5; }
.sc { font-family: Archivo, Inter, Arial, sans-serif; font-weight: 900; color: #9DB7B1; white-space: nowrap; margin-left: .3em; }
.sc i { font-style: normal; display: inline-block; min-width: 1.25em; text-align: right; }
.win .sc { color: #D9F45A; }
.foot { white-space: nowrap; overflow: hidden; display: -webkit-flex; display: flex; -webkit-justify-content: space-between; justify-content: space-between; color: #9DB7B1; font-size: .6em; font-weight: 600; margin-top: .3em; }
.foot .warn { color: #F5B54A; }
.msg { margin: auto; text-align: center; color: #C3D8D3; font-size: 1.2em; max-width: 30em; }
.demo { position: fixed; bottom: 1.6em; left: 0; right: 0; text-align: center; }
.demo span { background: #F5B54A; color: #2a1b02; font: 800 14px Inter, Arial, sans-serif; padding: 4px 12px; border-radius: 99px; }
.awake { position: fixed; right: 0; bottom: 0; width: 2px; height: 2px; opacity: .01; pointer-events: none; }

/* newer browsers: a court's cards slide up one place when a result comes in */
:root { view-transition-name: none; }
.slot { view-transition-class: tv-slot; }
::view-transition-group(.tv-slot) { animation-duration: .9s; animation-timing-function: cubic-bezier(.3,.7,.2,1); }
::view-transition-new(.tv-slot):only-child { animation: tv-in .9s ease-out; }
::view-transition-old(.tv-slot):only-child { animation: tv-out .6s ease-in forwards; }
@keyframes tv-in { from { opacity: 0; transform: translateY(40%); } }
@keyframes tv-out { to { opacity: 0; transform: translateY(-60%); } }
@media (prefers-reduced-motion: reduce) {
  ::view-transition-group(*), ::view-transition-old(*), ::view-transition-new(*) { animation: none !important; }
  .dot { animation: none; }
}
</style>
</head>
<body>
@if(request()->has('demo'))<div class="demo"><span>BANDOMASIS REŽIMAS – laikai perkelti į dabar</span></div>@endif
<header class="top">
  @php $ball = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/app-ball.png'))); @endphp
  {{-- the tournament's own logo from Tournated when there is one; the padel ball otherwise (also if the logo fails to load) --}}
  @if(! empty($logo))
    <img class="logo" src="{{ $logo }}" alt="" onerror="this.onerror=null;this.className='';this.src='{{ $ball }}'">
  @else
    <img src="{{ $ball }}" alt="">
  @endif
  <h1>{{ $title }}</h1>
  <div><div class="clock" id="clock">--:--</div><div class="fresh" id="fresh"></div></div>
</header>
<main class="board" id="board"><p class="msg">Kraunamas grafikas…</p></main>
<footer class="foot">
  <span>„apie“ – numatomas laikas pagal įvestus rezultatus <span class="warn" id="warn"></span></span>
  <span>Telefone: padelioturnyrai.lt · programėlė „Padelio turnyrai“</span>
</footer>
{{-- a tiny silent looping video keeps many TVs from dimming or starting the screensaver --}}
<video class="awake" id="awake" muted loop playsinline autoplay src="data:video/mp4;base64,{{ base64_encode(file_get_contents(resource_path('images/keepawake.mp4'))) }}"></video>
<script>
(function () {
  'use strict';
  var DATA_URL = @json(route('schedule.tv.data', $id)) + @json($dataQuery !== '' ? '?'.$dataQuery : '');
  var POLL_MS = 15000;
  var MAX_SLOTS = 12; // per court; the ones that do not fit are hidden
  var board = document.getElementById('board');
  var data = null, lastKey = '', failed = false;
  var skew = null, tzOffset = 120; // server clock vs this TV (old TVs often have the wrong time), Vilnius offset in minutes

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  /** Venue time from the server's clock: { date: "2026-11-26", min: 845, hm: "14:05" }. */
  function now() {
    var d = new Date(Date.now() + (skew || 0) + tzOffset * 60000);
    var h = d.getUTCHours(), mi = d.getUTCMinutes();
    return { date: d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()), min: h * 60 + mi, hm: pad(h) + ':' + pad(mi) };
  }
  function toMin(t) { var m = /^(\d{1,2}):(\d{2})/.exec(t || ''); return m ? +m[1] * 60 + +m[2] : 1e9; }
  function played(m) { return !!((m.sets && m.sets.length) || m.winner_side || m.is_walkover); }
  function startMin(m) { return toMin(!played(m) && m.eta ? m.eta : m.time); }
  function courtNo(m) { var d = /\d+/.exec((m.court && m.court.name) || ''); return d ? +d[0] : 999; }
  function courtKey(m) { return m.court ? String(m.court.court_id || m.court.name) : ''; }
  /** "Court 4" -> "4 kortas"; "Skycop 1" stays as named in Tournated. */
  function courtTitle(name) { var m = /^court\s*(\d+)$/i.exec(name || ''); return m ? m[1] + ' kortas' : (name || ''); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function key(s) {
    s = String(s || '');
    if (s.normalize) s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');
    return s.toLowerCase().replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '');
  }
  // "Vaikinai U10 [LC]" -> "Vaikinai U10"; club-league "Moterys TOP 1" stays
  function group(m) { return String(m.division || '').replace(/\s*\[[^\]]*\]\s*/g, ' ').replace(/^\s+|\s+$/g, ''); }
  function indexOf(list, v) { for (var i = 0; i < list.length; i++) if (list[i] === v) return i; return -1; }

  /** Text size for the people furthest away; shrinks only if the columns could not hold a name at all. */
  function fit(n) {
    var share = (data && data.font_share) || 0.036;
    var h = window.innerHeight || document.documentElement.clientHeight;
    var w = window.innerWidth || document.documentElement.clientWidth;
    var wanted = share * h;
    var widest = (w * 0.98) / n / (0.58 * 15); // ~15 characters of a name must fit
    var px = Math.min(wanted, widest);
    document.body.style.fontSize = px.toFixed(1) + 'px';
    document.getElementById('warn').textContent = px < wanted * 0.97
      ? '· tekstas sumažintas ' + Math.round((1 - px / wanted) * 100) + ' % (per daug kortų šiam atstumui)' : '';
  }

  /** The day to show: today if it has matches, else the next match day, else the last one. */
  function showDay(matches, today) {
    var days = [];
    matches.forEach(function (m) { if (m.date && m.time && m.court && indexOf(days, m.date) < 0) days.push(m.date); });
    days.sort();
    if (indexOf(days, today) >= 0) return today;
    for (var i = 0; i < days.length; i++) if (days[i] > today) return days[i];
    return days.length ? days[days.length - 1] : today;
  }

  function columns(d) {
    var n = now();
    var day = showDay(d.matches, n.date);
    var isToday = day === n.date;
    var ids = d.court_ids || [], nums = d.courts || [];
    var byCourt = {}, order = [];
    d.matches.forEach(function (m) {
      if (m.date !== day || !m.time || !m.court || !m.court.name) return;
      if (ids.length && indexOf(ids, +m.court.court_id) < 0) return;
      if (!ids.length && nums.length && indexOf(nums, courtNo(m)) < 0) return;
      var k = courtKey(m);
      if (!byCourt[k]) { byCourt[k] = { name: m.court.name, id: +m.court.court_id, list: [] }; order.push(k); }
      byCourt[k].list.push(m);
    });
    order.sort(function (a, b) {
      var x = byCourt[a], y = byCourt[b];
      if (ids.length) return indexOf(ids, x.id) - indexOf(ids, y.id);
      if (nums.length) return indexOf(nums, courtNo(x.list[0])) - indexOf(nums, courtNo(y.list[0]));
      // "Skycop 2" before "Skycop 10", venues grouped by name
      var ax = x.name.replace(/\d+/g, ''), bx = y.name.replace(/\d+/g, '');
      return ax < bx ? -1 : ax > bx ? 1 : courtNo(x.list[0]) - courtNo(y.list[0]);
    });
    return order.slice(0, 8).map(function (k) {
      var c = byCourt[k];
      var list = c.list.sort(function (a, b) { return toMin(a.time) - toMin(b.time); });
      var done = list.filter(played), pending = list.filter(function (m) { return !played(m); });
      var live = isToday && pending[0] && (pending[0].is_match_in_progress || startMin(pending[0]) <= n.min) ? pending[0] : null;
      var rest = live ? pending.slice(1) : pending;
      var slots = [{ kind: 'done', m: done[done.length - 1] || null }];
      if (live) slots.push({ kind: 'live', m: live });
      rest.slice(0, MAX_SLOTS).forEach(function (m, i) { slots.push({ kind: !live && i === 0 && isToday ? 'call' : 'next', m: m }); });
      if (!pending.length) slots.push({ kind: 'end', m: null });
      return { title: courtTitle(c.name), slots: slots };
    });
  }

  // "Jonas Augustas" + "Kliukas" -> "J. Kliukas": the first initial and the full surname
  function person(p, place) {
    var first = String(p.name || '').replace(/^\s+|\s+$/g, '');
    var shown = first && p.surname ? first.charAt(0) + '. ' + p.surname : (first + ' ' + (p.surname || '')).replace(/^\s+|\s+$/g, '');
    return '<div class="p"><span class="n">' + esc(shown) + '</span>' + (place ? '<span class="pl">' + esc(place) + '</span>' : '') + '</div>';
  }

  function sideHtml(m, side, places) {
    var team = side === 1 ? m.team1 : m.team2;
    var people = (m.participants || []).filter(function (p) { return p.side === side && (p.name || p.surname); });
    var club = team && team.title;
    var who = people.length
      ? (club ? '<div class="club">' + esc(club) + '</div>' : '') + people.map(function (p) { return person(p, club ? '' : places[key((p.name || '') + ' ' + (p.surname || ''))]); }).join('')
      : '<div class="p"><span class="n">' + esc(club || 'Nepaskelbta') + '</span></div>';
    var games = played(m) ? (m.sets || []).map(function (s) { return '<i>' + (side === 1 ? s.side1 : s.side2) + '</i>'; }).join('') : '';
    var won = m.winner_side === side;
    return '<div class="side' + (won ? ' win' : '') + '"><div class="who">' + who + '</div><div class="sc">' + (games || (m.is_walkover && won ? '<i>W.O.</i>' : '')) + '</div></div>';
  }

  function slotHtml(s, places) {
    if (!s.m) {
      return '<div class="slot empty"><div class="lab">' + (s.kind === 'done' ? 'Dar nesužaista' : 'Daugiau mačų nėra') + '</div></div>';
    }
    var m = s.m;
    var est = !played(m) && m.eta && m.eta !== m.time;
    var t = esc(est ? m.eta : m.time);
    var late = est && toMin(m.eta) > toMin(m.time) ? '<span class="late">planuota ' + esc(m.time) + '</span>' : '';
    // every start still to come is an estimate: say so in words, not with a symbol people must decode
    var time = { done: '', live: '<small>nuo</small> ' + t, call: '<small>apie</small> ' + t, next: '<small>apie</small> ' + t }[s.kind];
    var status = { done: 'Baigta', live: '<span class="dot"></span>Žaidžia', call: 'Ruoškitės', next: 'Toliau' }[s.kind];
    var g = group(m);
    return '<div class="slot ' + s.kind + '" style="view-transition-name:m' + (+m.match_id) + '">'
      + '<div class="grp"><b class="st">' + status + '</b>' + (g ? '<span class="g">' + (s.kind === 'live' || s.kind === 'call' ? '' : ' · ') + esc(g) + '</span>' : '') + '</div>'
      + (time ? '<div class="lab"><span class="t">' + time + '</span>' + late + '</div>' : '')
      + sideHtml(m, 1, places) + sideHtml(m, 2, places) + '</div>';
  }

  /** Hide the cards that do not fully fit in their column (as many as the screen holds). */
  function trim() {
    var cols = board.querySelectorAll('.court'), i, j;
    for (i = 0; i < cols.length; i++) {
      var bottom = cols[i].getBoundingClientRect().bottom, full = false;
      var slots = cols[i].querySelectorAll('.slot');
      for (j = 0; j < slots.length; j++) {
        slots[j].className = slots[j].className.replace(/\s*\bhid\b/g, '');
        if (full || slots[j].getBoundingClientRect().bottom > bottom + 1) { slots[j].className += ' hid'; full = true; }
      }
    }
    // a cut place ("Klai…") reads wrong from afar — show it whole or not at all
    var pls = board.querySelectorAll('.pl');
    for (i = 0; i < pls.length; i++) {
      pls[i].className = 'pl';
      if (pls[i].scrollWidth > pls[i].clientWidth + 1) pls[i].className = 'pl hid';
    }
  }

  function paint(html, n) {
    fit(n);
    board.innerHTML = html;
    trim();
  }

  function render() {
    if (!data) return;
    var cols = columns(data);
    if (!cols.length) {
      board.innerHTML = '<p class="msg">' + (data.matches.length ? 'Šiandien mačų grafike nėra.' : 'Grafikas dar nepaskelbtas.') + '</p>';
      lastKey = '';
      return;
    }
    var n = Math.max(cols.length, 2);
    var places = data.places || {};
    var html = cols.map(function (c, i) {
      return '<section class="court c' + (i % 6 + 1) + '"><h2>' + esc(c.title) + '</h2>' + c.slots.map(function (s) { return slotHtml(s, places); }).join('') + '</section>';
    }).join('');
    // which match sits in which place on each court — a change here slides that column up
    var k = cols.map(function (c) { return c.slots.map(function (s) { return (s.m ? s.m.match_id : '-') + s.kind; }).join(','); }).join('|');
    var moved = lastKey && k !== lastKey;
    lastKey = k;
    if (moved && document.startViewTransition && !document.hidden) {
      document.startViewTransition(function () { paint(html, n); });
    } else {
      paint(html, n);
    }
  }

  /**
   * ?demo=1 — for trying the screen on a TV when nothing is being played: the
   * busiest day of the real schedule is moved to today (first match 100 min
   * ago), later results are hidden. Only on this screen; nothing is saved.
   */
  var DEMO = /[?&]demo(=|&|$)/.test(location.search);
  var demoShift = null;
  function demo(d) {
    var n = now(), counts = {}, day = null, best = 0;
    // placeholder times like 00:00 would throw the whole day off
    function ok(m) { return m.date && m.time && m.court && toMin(m.time) >= 7 * 60; }
    d.matches.forEach(function (m) { if (ok(m)) counts[m.date] = (counts[m.date] || 0) + 1; });
    for (var dd in counts) if (counts[dd] > best) { best = counts[dd]; day = dd; }
    if (!day) return d;
    var list = d.matches.filter(function (m) { return m.date === day && ok(m); });
    var first = Math.min.apply(null, list.map(function (m) { return toMin(m.time); }));
    if (demoShift === null) demoShift = n.min - 100 - first; // fixed once, so the demo day moves on with the clock
    function hm(v) { return pad(Math.floor(v / 60)) + ':' + pad(v % 60); }
    // whatever would run past midnight is left out instead of wrapping to 00:xx
    d.matches = list.filter(function (m) { return toMin(m.time) + demoShift < 1440 && toMin(m.time) + demoShift >= 0; }).map(function (m) {
      var x = {}, f;
      for (f in m) x[f] = m[f];
      var t = toMin(m.time) + demoShift;
      x.date = n.date; x.time = hm(t); x.eta = m.eta ? hm(toMin(m.eta) + demoShift) : null; x.is_match_in_progress = false;
      if (t > n.min - 35) { x.sets = []; x.winner_side = null; x.is_walkover = false; }
      return x;
    });
    return d;
  }

  function load() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', DATA_URL + (DATA_URL.indexOf('?') < 0 ? '?' : '&') + '_=' + Date.now(), true);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.timeout = 12000;
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      try {
        if (xhr.status !== 200) throw new Error(xhr.status);
        var d = JSON.parse(xhr.responseText);
        if (d.server_ms) skew = d.server_ms - Date.now();
        if (typeof d.tz_offset_min === 'number') tzOffset = d.tz_offset_min;
        data = DEMO ? demo(d) : d;
        failed = false;
        render();
      } catch (e) {
        failed = true;
      }
      tick();
    };
    xhr.send();
  }

  function tick() {
    document.getElementById('clock').textContent = skew === null ? '--:--' : now().hm;
    var f = document.getElementById('fresh');
    if (failed) { f.textContent = 'Nėra ryšio – rodomi paskutiniai duomenys'; f.className = 'fresh bad'; return; }
    var ago = data && data.synced_at ? Math.max(0, Math.round((Date.now() + (skew || 0) - Date.parse(data.synced_at)) / 60000)) : null;
    f.textContent = ago === null || isNaN(ago) ? '' : ago < 1 ? 'Atnaujinta ką tik' : 'Atnaujinta prieš ' + ago + ' min';
    f.className = 'fresh';
  }

  // keep the TV awake: the Wake Lock API where it exists, the silent looping video everywhere
  function wake() {
    try { if (navigator.wakeLock && !document.hidden) navigator.wakeLock.request('screen')['catch'](function () {}); } catch (e) { /* not allowed here */ }
    var v = document.getElementById('awake');
    try { if (v && v.paused) { var p = v.play(); if (p && p['catch']) p['catch'](function () {}); } } catch (e) { /* no video here */ }
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { wake(); load(); } });
  window.addEventListener('resize', function () { lastKey = ''; render(); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { if (data) trim(); });

  tick();
  load();
  wake();
  setInterval(load, POLL_MS);
  setInterval(tick, 5000);
  setInterval(render, 30000); // "playing now" also changes with the clock
  setInterval(wake, 60000);
  setTimeout(function () { location.reload(); }, 6 * 3600 * 1000); // pick up page updates on long days
})();
</script>
</body>
</html>
