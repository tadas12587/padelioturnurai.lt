<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>TV · {{ $title }}</title>
<meta name="theme-color" content="#0A2226">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
{{-- fonts must never hold the schedule back on a slow venue network: load them without blocking --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@600;700;800&display=swap" media="print" onload="this.media='all'">
<style>
:root {
  --ground: #0A2226; --surface: #0E2C30; --line: rgba(238,244,242,.14);
  --ink: #F2F7F5; --soft: #C3D8D3; --muted: #9DB7B1; --ball: #D9F45A; --ball-ink: #17230A; --warn: #F5B54A;
  --n: 4;
  color-scheme: dark;
  /* only the court cards animate, not the whole page */
  view-transition-name: none;
}
* { box-sizing: border-box; margin: 0; }
html, body { height: 100%; background: var(--ground); color: var(--ink); overflow: hidden; }
body {
  /* set from the TV size and viewing distance (ADA 703.5.5), see fit() */
  font-size: 2.6vh;
  font-family: Inter, system-ui, sans-serif; font-weight: 700; font-variant-numeric: tabular-nums;
  display: flex; flex-direction: column; padding: .5em .6em .35em; gap: .45em;
}
.top { display: flex; align-items: center; gap: .6em; font-family: Archivo, Inter, sans-serif; font-size: .8em; }
.top img { width: 1.7em; height: 1.7em; }
.top h1 { font-size: 1.15em; font-weight: 900; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.clock { font-size: 1.6em; font-weight: 900; color: var(--ball); line-height: 1; }
.fresh { color: var(--muted); font-size: .7em; text-align: right; font-family: Inter, sans-serif; }
.fresh.bad { color: var(--warn); }

.board { flex: 1; min-height: 0; display: grid; grid-template-columns: repeat(var(--n), minmax(0, 1fr)); gap: .45em; }
.court { min-width: 0; min-height: 0; display: flex; flex-direction: column; gap: .3em; overflow: hidden; }
.court h2 {
  flex: none; font-family: Archivo, Inter, sans-serif; font-weight: 900; font-size: 1.1em; text-transform: uppercase; letter-spacing: .03em;
  background: var(--c, var(--ink)); color: var(--ground); border-radius: .25em; padding: .05em .4em;
}
/* every court its own colour (light, so dark text stays readable) — easier to find yours from afar */
.court:nth-child(6n+1) { --c: #EAF3F1; } .court:nth-child(6n+2) { --c: #8FD3FF; } .court:nth-child(6n+3) { --c: #FFB38A; }
.court:nth-child(6n+4) { --c: #C9B6FF; } .court:nth-child(6n+5) { --c: #86E3B5; } .court:nth-child(6n+6) { --c: #FFD66B; }
.court { border-top: .12em solid var(--c, transparent); padding-top: .2em; }
.slot {
  flex: none; border-radius: .3em; padding: .25em .4em .3em; display: flex; flex-direction: column; gap: .12em;
  background: var(--surface); border: .06em solid var(--line); view-transition-class: tv-slot;
}
.slot[hidden] { display: none; }
/* already played: smaller, people only glance at the score */
.slot.done { background: transparent; font-size: .72em; }
.slot.live { background: #14444A; border: .12em solid var(--ball); }
.slot.call { background: var(--ball); color: var(--ball-ink); border: .12em solid transparent; }
.slot.empty { color: var(--muted); border-style: dashed; background: transparent; font-size: .8em; }
/* status + time: the line people look for first */
.lab { display: flex; align-items: baseline; gap: .4em; white-space: nowrap; overflow: hidden; font-family: Archivo, Inter, sans-serif; font-weight: 900; text-transform: uppercase; }
.lab .st { font-size: .7em; letter-spacing: .04em; color: var(--muted); min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.lab .t { flex: none; }
.live .lab .st { color: var(--ball); }
.call .lab .st { color: var(--ball-ink); }
.lab .t { font-size: 1.05em; }
.lab .t small { font-size: .62em; font-weight: 800; text-transform: none; color: var(--soft); }
.call .lab .t small { color: #33440f; }
.live .lab .t { color: var(--ball); }
.lab .late { font-family: Inter, sans-serif; text-transform: none; color: var(--warn); font-size: .6em; font-weight: 700; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.call .lab .late { color: #5b4300; }
.grp { font-size: .7em; font-weight: 800; color: var(--soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.grp .st { font-family: Archivo, Inter, sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; color: var(--ink); }
.live .grp .st { color: var(--ball); }
.call .grp .st { color: var(--ball-ink); }
.grp .g::before { content: " · "; }
/* on the cards people act on, the group gets its own line instead of being cut */
.live .grp, .call .grp { display: flex; flex-direction: column; line-height: 1.15; }
.live .grp .g, .call .grp .g { overflow: hidden; text-overflow: ellipsis; }
.live .grp .g::before, .call .grp .g::before { content: none; }
.call .grp { color: #33440f; }
.dot { display: inline-block; width: .55em; height: .55em; border-radius: 50%; background: var(--ball); margin-right: .3em; animation: pulse 1.6s ease-in-out infinite; }
@keyframes pulse { 50% { opacity: .35; } }
.side { display: flex; align-items: center; gap: .3em; }
.side + .side { border-top: .05em solid var(--line); padding-top: .1em; }
.call .side + .side { border-color: rgba(23,35,10,.25); }
.who { flex: 1; min-width: 0; }
.p { display: flex; gap: .35em; align-items: baseline; min-width: 0; line-height: 1.15; }
/* the name wins the space; the place takes what is left */
.p { font-size: .9em; }
.p .n { font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 0 1 auto; max-width: 100%; }
.p .pl[hidden] { display: none; }
.p .pl { color: var(--soft); font-size: .78em; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1 1 0; min-width: 0; }
.call .p .pl, .call .club { color: #33440f; }
.club { font-size: .72em; font-weight: 800; color: var(--ball); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.done .p .n { font-weight: 700; color: var(--soft); }
.done .p .pl { font-size: .85em; }
.done .win .n { color: var(--ink); }
.sc { display: flex; gap: .15em; font-family: Archivo, Inter, sans-serif; font-weight: 900; color: var(--muted); }
.sc i { font-style: normal; min-width: 1.25em; text-align: right; }
.win .sc { color: var(--ball); }
.foot { white-space: nowrap; overflow: hidden; display: flex; justify-content: space-between; gap: 1em; color: var(--muted); font-size: .6em; font-weight: 600; }
.foot .warn { color: var(--warn); }
.msg { margin: auto; text-align: center; color: var(--soft); font-size: 1.2em; max-width: 30em; }

/* a result came in: the court's cards slide up one place */
::view-transition-group(.tv-slot) { animation-duration: .9s; animation-timing-function: cubic-bezier(.3,.7,.2,1); }
::view-transition-new(.tv-slot):only-child { animation: tv-in .9s ease-out; }
::view-transition-old(.tv-slot):only-child { animation: tv-out .6s ease-in forwards; }
@keyframes tv-in { from { opacity: 0; translate: 0 40%; } }
@keyframes tv-out { to { opacity: 0; translate: 0 -60%; } }
@media (prefers-reduced-motion: reduce) {
  ::view-transition-group(*), ::view-transition-old(*), ::view-transition-new(*) { animation: none !important; }
  .dot { animation: none; }
}
</style>
</head>
<body>
@if(request()->has('demo'))<div style="position:fixed;bottom:1.6em;left:50%;translate:-50% 0;background:#F5B54A;color:#2a1b02;font:800 14px Inter,sans-serif;padding:4px 12px;border-radius:99px;z-index:9">BANDOMASIS REŽIMAS – laikai perkelti į dabar</div>@endif
<header class="top">
  <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('images/app-ball.png'))) }}" alt="">
  <h1>{{ $title }}</h1>
  <div><div class="clock" id="clock">--:--</div><div class="fresh" id="fresh"></div></div>
</header>
<main class="board" id="board"><p class="msg">Kraunamas grafikas…</p></main>
<footer class="foot">
  <span>„apie“ – numatomas laikas pagal įvestus rezultatus <span class="warn" id="warn"></span></span>
  <span>Telefone: padelioturnyrai.lt · programėlė „Padelio turnyrai“</span>
</footer>
<script>
(() => {
  const DATA_URL = @json(route('schedule.tv.data', $id)) + @json($dataQuery !== '' ? '?'.$dataQuery : '');
  const TZ = 'Europe/Vilnius';
  const POLL_MS = 15000;
  const MAX_SLOTS = 12; // per court; the ones that do not fit are hidden
  const board = document.getElementById('board');
  let data = null, lastKey = '', failed = false;

  const fmt = new Intl.DateTimeFormat('lt-LT', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
  function now() {
    const p = Object.fromEntries(fmt.formatToParts(new Date()).map(x => [x.type, x.value]));
    return { date: `${p.year}-${p.month}-${p.day}`, min: +p.hour * 60 + +p.minute, hm: `${p.hour}:${p.minute}` };
  }
  const toMin = t => { const m = /^(\d{1,2}):(\d{2})/.exec(t || ''); return m ? +m[1] * 60 + +m[2] : 1e9; };
  const played = m => !!((m.sets && m.sets.length) || m.winner_side || m.is_walkover);
  const startMin = m => toMin(!played(m) && m.eta ? m.eta : m.time);
  const courtNo = m => { const d = /\d+/.exec((m.court && m.court.name) || ''); return d ? +d[0] : 999; };
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const key = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
  const short = full => { const w = full.split(/\s+/); return w.length > 1 ? `${w[0][0]}. ${w.slice(1).join(' ')}` : full; };
  // "Vaikinai U10 [LC]" -> "Vaikinai U10"; club-league "Moterys TOP 1" stays
  const group = m => String(m.division || '').replace(/\s*\[[^\]]*\]\s*/g, ' ').trim();

  /** Text size for the people furthest away; shrinks only if the columns could not hold a name at all. */
  function fit(n) {
    const share = (data && data.font_share) || 0.036;
    const wanted = share * innerHeight;
    const colWidth = (innerWidth * 0.98) / n;
    const widest = colWidth / (0.58 * 15); // ~15 characters of a name must fit
    const px = Math.min(wanted, widest);
    document.body.style.fontSize = px.toFixed(1) + 'px';
    document.getElementById('warn').textContent = px < wanted * 0.97
      ? `· tekstas sumažintas ${Math.round((1 - px / wanted) * 100)} % (per daug kortų šiam atstumui)` : '';
  }

  /** The day to show: today if it has matches, else the next match day, else the last one. */
  function showDay(matches, today) {
    const days = [...new Set(matches.filter(m => m.date && m.time && m.court).map(m => m.date))].sort();
    return days.includes(today) ? today : (days.find(d => d > today) || days[days.length - 1] || today);
  }

  function columns(d) {
    const n = now();
    const day = showDay(d.matches, n.date);
    const isToday = day === n.date;
    const byCourt = new Map();
    for (const m of d.matches) {
      if (m.date !== day || !m.time || !m.court || !m.court.name) continue;
      const c = courtNo(m);
      if (d.courts.length && !d.courts.includes(c)) continue;
      if (!byCourt.has(c)) byCourt.set(c, []);
      byCourt.get(c).push(m);
    }
    const order = d.courts.length ? d.courts.filter(c => byCourt.has(c)) : [...byCourt.keys()].sort((a, b) => a - b);
    return order.slice(0, 8).map(c => {
      const list = byCourt.get(c).sort((a, b) => toMin(a.time) - toMin(b.time));
      const done = list.filter(played), pending = list.filter(m => !played(m));
      const live = isToday && pending[0] && (pending[0].is_match_in_progress || startMin(pending[0]) <= n.min) ? pending[0] : null;
      const rest = live ? pending.slice(1) : pending;
      const slots = [{ kind: 'done', m: done[done.length - 1] || null }];
      if (live) slots.push({ kind: 'live', m: live });
      rest.slice(0, MAX_SLOTS).forEach((m, i) => slots.push({ kind: !live && i === 0 && isToday ? 'call' : 'next', m }));
      if (!pending.length) slots.push({ kind: 'end', m: null });
      return { court: c, slots };
    });
  }

  // "Jonas Augustas" + "Kliukas" -> "J. Kliukas": the first initial and the full surname
  function person(p, place) {
    const first = String(p.name || '').trim();
    const shown = first && p.surname ? `${first[0]}. ${p.surname}` : short(`${first} ${p.surname || ''}`.trim());
    return `<div class="p"><span class="n">${esc(shown)}</span>${place ? `<span class="pl">${esc(place)}</span>` : ''}</div>`;
  }

  function sideHtml(m, side, places) {
    const team = side === 1 ? m.team1 : m.team2;
    const people = (m.participants || []).filter(p => p.side === side && (p.name || p.surname));
    const club = team && team.title;
    const who = people.length
      ? (club ? `<div class="club">${esc(club)}</div>` : '') + people.map(p => person(p, club ? '' : places[key(`${p.name || ''} ${p.surname || ''}`)])).join('')
      : `<div class="p"><span class="n">${esc(club || 'Nepaskelbta')}</span></div>`;
    const games = played(m) ? (m.sets || []).map(s => `<i>${side === 1 ? s.side1 : s.side2}</i>`).join('') : '';
    const won = m.winner_side === side;
    return `<div class="side${won ? ' win' : ''}"><div class="who">${who}</div><div class="sc">${games || (m.is_walkover && won ? '<i>W.O.</i>' : '')}</div></div>`;
  }

  function slotHtml(s, places) {
    if (!s.m) {
      const text = s.kind === 'done' ? 'Dar nesužaista' : 'Daugiau mačų nėra';
      return `<div class="slot empty"><div class="lab"><span class="st">${text}</span></div></div>`;
    }
    const m = s.m;
    const est = !played(m) && m.eta && m.eta !== m.time;
    const t = est ? m.eta : m.time;
    const late = est && toMin(m.eta) > toMin(m.time) ? `<span class="late">planuota ${esc(m.time)}</span>` : '';
    const time = {
      done: '',
      live: `<small>nuo</small> ${esc(t)}`,
      // every start still to come is an estimate: say so in words, not with a symbol people must decode
      call: `<small>apie</small> ${esc(t)}`,
      next: `<small>apie</small> ${esc(t)}`,
    }[s.kind];
    const status = { done: 'Baigta', live: '<span class="dot"></span>Žaidžia', call: 'Ruoškitės', next: 'Toliau' }[s.kind];
    const g = group(m);
    return `<div class="slot ${s.kind}" style="view-transition-name:m${+m.match_id}">`
      + `<div class="grp"><b class="st">${status}</b>${g ? `<span class="g">${esc(g)}</span>` : ''}</div>`
      + (time ? `<div class="lab"><span class="t">${time}</span>${late}</div>` : '')
      + sideHtml(m, 1, places) + sideHtml(m, 2, places) + '</div>';
  }

  /** Hide the cards that do not fully fit in their column (as many as the screen holds). */
  function trim() {
    for (const col of board.querySelectorAll('.court')) {
      const bottom = col.getBoundingClientRect().bottom;
      let full = false;
      for (const s of col.querySelectorAll('.slot')) {
        s.hidden = false;
        if (full || s.getBoundingClientRect().bottom > bottom + 1) { s.hidden = true; full = true; }
      }
    }
    // a cut place ("Klai…") reads wrong from afar — show it whole or not at all
    for (const pl of board.querySelectorAll('.pl')) {
      pl.hidden = false;
      if (pl.scrollWidth > pl.clientWidth + 1) pl.hidden = true;
    }
  }

  function paint(html, n) {
    document.documentElement.style.setProperty('--n', n);
    fit(n);
    board.innerHTML = html;
    trim();
  }

  function render() {
    if (!data) return;
    const cols = columns(data);
    if (!cols.length) {
      board.innerHTML = '<p class="msg">Šiandien mačų grafike nėra.</p>';
      lastKey = '';
      return;
    }
    const n = Math.max(cols.length, 2);
    const places = data.places || {};
    const html = cols.map(c => `<section class="court"><h2>${c.court} kortas</h2>${c.slots.map(s => slotHtml(s, places)).join('')}</section>`).join('');
    // which match sits in which place on each court — a change here slides that column up
    const k = cols.map(c => c.slots.map(s => (s.m ? s.m.match_id : '-') + s.kind).join(',')).join('|');
    const moved = lastKey && k !== lastKey;
    lastKey = k;
    if (moved && document.startViewTransition && !document.hidden) {
      document.startViewTransition(() => paint(html, n));
    } else {
      paint(html, n);
    }
  }

  /**
   * ?demo=1 — for trying the screen on a TV when nothing is being played: the
   * busiest day of the real schedule is moved to today (first match 100 min
   * ago), later results are hidden. Only on this screen; nothing is saved.
   */
  const DEMO = new URLSearchParams(location.search).has('demo');
  let demoShift = null;
  function demo(d) {
    const n = now();
    const counts = {};
    for (const m of d.matches) if (m.date && m.time && m.court) counts[m.date] = (counts[m.date] || 0) + 1;
    const day = Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0];
    if (!day) return d;
    const list = d.matches.filter(m => m.date === day && m.time && m.court);
    const first = Math.min(...list.map(m => toMin(m.time)));
    if (demoShift === null) demoShift = n.min - 100 - first; // fixed once, so the demo day moves on with the clock
    const hm = v => `${String(Math.floor(((v % 1440) + 1440) % 1440 / 60)).padStart(2, '0')}:${String(((v % 60) + 60) % 60).padStart(2, '0')}`;
    d.matches = list.map(m => {
      const t = toMin(m.time) + demoShift;
      const x = { ...m, date: n.date, time: hm(t), eta: m.eta ? hm(toMin(m.eta) + demoShift) : undefined, is_match_in_progress: false };
      if (t > n.min - 35) Object.assign(x, { sets: [], winner_side: null, is_walkover: false });
      return x;
    });
    return d;
  }

  async function load() {
    try {
      const r = await fetch(DATA_URL, { cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!r.ok) throw new Error(r.status);
      data = await r.json();
      if (DEMO) data = demo(data);
      failed = false;
      render();
    } catch (e) {
      failed = true;
    }
    tick();
  }

  function tick() {
    const n = now();
    document.getElementById('clock').textContent = n.hm;
    const f = document.getElementById('fresh');
    if (failed) { f.textContent = 'Nėra ryšio – rodomi paskutiniai duomenys'; f.className = 'fresh bad'; return; }
    const ago = data && data.synced_at ? Math.max(0, Math.round((Date.now() - Date.parse(data.synced_at)) / 60000)) : null;
    f.textContent = ago === null ? '' : ago < 1 ? 'Atnaujinta ką tik' : `Atnaujinta prieš ${ago} min`;
    f.className = 'fresh';
  }

  // keep the TV awake where the browser allows it
  async function wake() {
    try { if ('wakeLock' in navigator && !document.hidden) await navigator.wakeLock.request('screen'); } catch (e) { /* not allowed here */ }
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { wake(); load(); } });
  addEventListener('resize', () => { lastKey = ''; render(); });
  if (document.fonts) document.fonts.ready.then(() => { if (data) trim(); });

  tick();
  load();
  wake();
  setInterval(load, POLL_MS);
  setInterval(tick, 5000);
  setInterval(render, 30000); // "playing now" also changes with the clock
  setTimeout(() => location.reload(), 6 * 3600 * 1000); // pick up page updates on long days
})();
</script>
</body>
</html>
