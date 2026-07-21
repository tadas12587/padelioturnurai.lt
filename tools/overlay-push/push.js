// ============================================================
//  Padelioturnyrai.lt – Overlay duomenų "push" scenarijus
// ------------------------------------------------------------
//  Paleidžiamas TAVO kompiuteryje (jis turi internetą, serveris ne).
//  Kas POLL_INTERVAL_MS nuskaito Tournated turnyro duomenis ir
//  nusiunčia juos į tavo svetainę (POST /overlay/ingest).
//
//  Kuriuos turnyrus siųsti, skriptas sužino iš serverio (pagal
//  overlay'us admin'e) — pakeitus turnyrą admin'e, nieko keisti/
//  perpaleisti nereikia.
//
//  Reikia: Node.js 18+ (turi įmontuotą fetch).
//  Paleidimas:
//      node push.js
//  arba su aplinkos kintamaisiais (TOURNAMENT_ID — tik atsarginis):
//      TOURNAMENT_ID=10424 INGEST_TOKEN=xxxx node push.js
// ============================================================

// ── Nustatymai (gali keisti čia arba per aplinkos kintamuosius) ──
const SITE_URL       = process.env.SITE_URL       || 'https://padelioturnyrai.lt';
const INGEST_TOKEN   = process.env.INGEST_TOKEN   || 'ugx490pqlkt3nycwmdojfeb5ahi6r2sz817v';   // turi sutapti su .env OVERLAY_INGEST_TOKEN
// Turnyrų ID sąrašą imame iš serverio (kuriuos naudoja overlay'ai admin'e).
// TOURNAMENT_ID — neprivalomas atsarginis variantas, jei serveris nepasiekiamas.
const TOURNAMENT_ID  = process.env.TOURNAMENT_ID  || '';
const POLL_INTERVAL_MS = Number(process.env.POLL_INTERVAL_MS || 30000);        // grafikas/rezultatai kas 30 s

const GRAPHQL_URL = 'https://api.tournated.com/graphql';
const ORIGIN      = 'https://play.padel.lt';

// Tournated prisijungimo tokenas (Bearer). Su juo atsirakina draws/groups/
// registracijos/dalyviai. Imamas iš env TOURNATED_TOKEN arba iš vietinio
// failo tools/overlay-push/.token (į git nepatenka). Pasibaigus galiojimui —
// skriptas savaime grįžta prie atkūrimo iš rungtynių.
let TOURNATED_TOKEN = process.env.TOURNATED_TOKEN || '';
try {
  if (!TOURNATED_TOKEN) {
    const fs = require('fs'), path = require('path');
    const f = path.join(__dirname, '.token');
    if (fs.existsSync(f)) TOURNATED_TOKEN = fs.readFileSync(f, 'utf8').trim().replace(/^Bearer\s+/i, '');
  }
} catch (_) { /* nesvarbu */ }

// ── GraphQL pagalbinė ───────────────────────────────────────
const GQL_TIMEOUT_MS = Number(process.env.GQL_TIMEOUT_MS || 15000);

async function gql(query, timeoutMs = GQL_TIMEOUT_MS) {
  const ac = new AbortController();
  const timer = setTimeout(() => ac.abort(), timeoutMs);
  let res;
  try {
    const headers = { 'Content-Type': 'application/json', 'Origin': ORIGIN };
    if (TOURNATED_TOKEN) headers.Authorization = `Bearer ${TOURNATED_TOKEN}`;
    res = await fetch(GRAPHQL_URL, {
      method: 'POST',
      headers,
      body: JSON.stringify({ query }),
      signal: ac.signal,
    });
  } catch (e) {
    if (e.name === 'AbortError') throw new Error(`API neatsakė per ${timeoutMs / 1000}s (Tournated pusės problema)`);
    throw new Error(`Tinklo klaida: ${e.message}`);
  } finally {
    clearTimeout(timer);
  }

  // 502/504 dažnai grąžina tuščią kūną — be šito gaudavosi „Unexpected end of JSON input".
  const text = await res.text();
  if (!text.trim()) throw new Error(`API grąžino tuščią atsakymą (HTTP ${res.status}) — Tournated pusės problema`);

  let json;
  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(`API grąžino ne JSON (HTTP ${res.status}): ${text.slice(0, 120)}`);
  }
  if (json.errors) throw new Error(JSON.stringify(json.errors));
  return json.data;
}

// ── Turnyro kategorijos ─────────────────────────────────────
async function fetchTournament(id) {
  // Trumpas laikas — šis resolveris Tournated pusėje šiuo metu kabo, nenorim
  // laukti pilno GQL_TIMEOUT kiekvieną kartą.
  const data = await gql(`{
    tournament(id: ${id}) {
      title
      tournamentCategory { id category { id name } mde }
    }
  }`, 5000);
  return data.tournament || null;
}

// ── Vienos kategorijos grupės ───────────────────────────────
async function fetchGroups(categoryId) {
  const data = await gql(`{
    groups(filter: { tournamentCategory: ${categoryId} }) {
      id name segment
      entries { id place registrationRequest { users { user { name surname } } } }
      matches { id status winner { id } }
    }
  }`);
  return data.groups || [];
}

// ── Vienos kategorijos dalyviai (poros) ─────────────────────
// Tournated grąžina po vieną eilutę kiekvienam žaidėjui; poros suporuojamos
// pagal team.id (dvejetuose abu žaidėjai dalijasi ta pačia komanda).
async function fetchParticipants(tournamentId, categoryId) {
  const data = await gql(`{
    tournamentRegistrationParticipants(tournament: ${tournamentId}, categoryId: ${categoryId}) {
      registrationId
      user { name surname }
      team { id }
    }
  }`);
  const rows = data.tournamentRegistrationParticipants || [];

  // A doubles pair shares one registrationId (the entry); `team` is each
  // player's personal team object, so group by registrationId.
  const byReg = new Map();
  for (const r of rows) {
    const key = r.registrationId != null ? `r${r.registrationId}` : ((r.team && r.team.id) || `u${Math.random()}`);
    if (!byReg.has(key)) byReg.set(key, []);
    const nm = `${r.user?.name || ''} ${r.user?.surname || ''}`.trim();
    if (nm) byReg.get(key).push(nm);
  }

  // Drop incomplete (partner-pending) doubles entries: if any entry has a full
  // pair, keep only entries of that size — mirrors the public participants list.
  const groups = [...byReg.entries()];
  const maxSize = groups.reduce((m, [, names]) => Math.max(m, names.length), 1);
  const minNeeded = maxSize >= 2 ? 2 : 1;

  const out = [];
  for (const [key, names] of groups) {
    if (names.length < minNeeded) continue;
    out.push({ id: key, name: names.join(' / ') || `#${key}`, seed: null, pot: null });
  }
  return out;
}

// ── Vienos kategorijos žaidynės (draws) ────────────────────
async function fetchDraws(categoryId) {
  // `draws` returns raw JSON objects; presence indicates an elimination/main draw.
  const data = await gql(`{ draws(filter: { tournamentCategory: ${categoryId} }) }`);
  return data.draws || [];
}

// ── Susitikimai (order of play) ─────────────────────────────
function normalizeMatch(m) {
  const names = (p) => (p && p.users)
    ? p.users.map((u) => `${u.name || ''} ${u.surname || ''}`.trim()).filter(Boolean) : [];
  const e1 = m.entry1 && m.entry1.id;
  const e2 = m.entry2 && m.entry2.id;
  const w = m.winner && m.winner.id;
  let winner = null;
  if (w != null && e1 != null && w === e1) winner = 1;
  else if (w != null && e2 != null && w === e2) winner = 2;
  return {
    id: m.id,
    date: (m.date || '').slice(0, 10),
    time: m.time || null,
    duration: m.duration || null,
    court: (m.court && m.court.name) || null,
    court_id: (m.court && m.court.id) || null,
    category_id: (m.tournamentCategory && m.tournamentCategory.id) || null,
    category: (m.tournamentCategory && m.tournamentCategory.category && m.tournamentCategory.category.name) || null,
    status: m.status || null,
    in_progress: !!m.isMatchInProgress,
    finished_at: m.firstScoreSubmittedAt || null,
    round: m.round || null,
    segment: m.bracketType || null,
    score: m.score || null,
    team1: names(m.participant1),
    team2: names(m.participant2),
    winner,
    // reikalinga lentelių/bracketų atkūrimui iš matches (kai draws/groups užrakinti)
    entry1_id: e1 ?? null,
    entry2_id: e2 ?? null,
    winner_entry_id: w != null ? w : null,
    group_id: (m.group && m.group.id) || null,
    group_name: (m.group && m.group.name) || null,
  };
}

// ── Rezultato eilutės parsinimas ("6:1 6:0" → setai/geimai) ──
function parseScore(score) {
  const sets = [];
  let sw1 = 0, sw2 = 0, g1 = 0, g2 = 0;
  String(score || '').trim().split(/\s+/).filter(Boolean).forEach((tok) => {
    const p = tok.replace(/[\[\]]/g, '').split(':');
    if (p.length === 2) {
      const a = parseInt(p[0], 10), b = parseInt(p[1], 10);
      if (!Number.isNaN(a) && !Number.isNaN(b)) {
        sets.push([a, b]); g1 += a; g2 += b;
        if (a > b) sw1++; else if (b > a) sw2++;
      }
    }
  });
  return { sets, sw1, sw2, g1, g2 };
}

// ── Grupių lentelės iš matches (kai „groups" grąžina tuščią) ──
// Standartinis padel rikiavimas: 1) pergalės, 2) tarpusavis, 3) setų sk., 4) geimų sk.
function buildGroupsFromMatches(matches, categoryId) {
  const inCat = matches.filter((m) => String(m.category_id) === String(categoryId) && m.group_id != null);
  if (!inCat.length) return [];

  const byGroup = new Map();
  for (const m of inCat) {
    const gid = String(m.group_id);
    if (!byGroup.has(gid)) byGroup.set(gid, { id: m.group_id, name: m.group_name || '', matches: [] });
    byGroup.get(gid).matches.push(m);
  }

  const usersOf = (names) => (names || []).map((full) => ({ user: { name: String(full), surname: '' } }));
  const out = [];
  for (const g of byGroup.values()) {
    const ent = new Map(); // entryId -> names[]
    const addEnt = (id, names) => { if (id != null && !ent.has(String(id))) ent.set(String(id), { id, names: names || [] }); };
    for (const m of g.matches) { addEnt(m.entry1_id, m.team1); addEnt(m.entry2_id, m.team2); }

    const st = {};
    for (const e of ent.values()) st[String(e.id)] = { id: e.id, w: 0, sw: 0, sl: 0, gw: 0, gl: 0, h2h: {} };
    for (const m of g.matches) {
      if ((m.status || '') !== 'completed') continue;
      const e1 = m.entry1_id, e2 = m.entry2_id;
      if (e1 == null || e2 == null) continue;
      const A = st[String(e1)], B = st[String(e2)];
      if (!A || !B) continue;
      const sc = parseScore(m.score);
      A.sw += sc.sw1; A.sl += sc.sw2; A.gw += sc.g1; A.gl += sc.g2;
      B.sw += sc.sw2; B.sl += sc.sw1; B.gw += sc.g2; B.gl += sc.g1;
      if (m.winner_entry_id === e1) { A.w++; A.h2h[String(e2)] = (A.h2h[String(e2)] || 0) + 1; }
      else if (m.winner_entry_id === e2) { B.w++; B.h2h[String(e1)] = (B.h2h[String(e1)] || 0) + 1; }
    }

    const rows = Object.values(st).sort((a, b) => {
      if (b.w !== a.w) return b.w - a.w;
      const ah = a.h2h[String(b.id)] || 0, bh = b.h2h[String(a.id)] || 0;
      if (ah !== bh) return bh - ah;
      const asd = a.sw - a.sl, bsd = b.sw - b.sl; if (bsd !== asd) return bsd - asd;
      return (b.gw - b.gl) - (a.gw - a.gl);
    });
    const placeById = {};
    rows.forEach((r, i) => { placeById[String(r.id)] = i + 1; });

    const entries = [...ent.values()].map((e) => ({
      id: e.id,
      place: placeById[String(e.id)] || null,
      registrationRequest: { users: usersOf(e.names) },
    }));
    const gmatches = g.matches.map((m) => ({
      id: m.id, status: m.status,
      winner: m.winner_entry_id != null ? { id: m.winner_entry_id } : null,
    }));
    out.push({ id: g.id, name: g.name, segment: null, entries, matches: gmatches });
  }
  out.sort((a, b) => String(a.name).localeCompare(String(b.name)));
  return out;
}

// ── Dalyviai iš matches (kai „participants" užrakinti) ───────
// Veikia tik jei kategorija jau turi rungtynes (po burtų/sėjos).
function buildParticipantsFromMatches(matches, categoryId) {
  const inCat = matches.filter((m) => String(m.category_id) === String(categoryId));
  const byEntry = new Map();
  const add = (id, names) => {
    if (id == null || byEntry.has(String(id))) return;
    const nm = (names || []).join(' / ');
    if (nm) byEntry.set(String(id), { id: `e${id}`, name: nm, seed: null, pot: null });
  };
  for (const m of inCat) { add(m.entry1_id, m.team1); add(m.entry2_id, m.team2); }
  return [...byEntry.values()];
}

// ── Bracketai iš matches (kai „draws" užrakinti) ─────────────
function buildBracketsFromMatches(matches, categoryId) {
  const inCat = matches.filter((m) => String(m.category_id) === String(categoryId) && m.segment && m.group_id == null);
  if (!inCat.length) return [];

  const roundRank = (r) => {
    const t = String(r || '').toLowerCase();
    const num = t.match(/^r?(\d+)$/); if (num) return parseInt(num[1], 10);
    if (/round of 64/.test(t)) return 1;
    if (/round of 32/.test(t)) return 2;
    if (/round of 16/.test(t)) return 3;
    if (/quarter/.test(t)) return 50;
    if (/semi/.test(t)) return 51;
    if (/\bfinal\b/.test(t) && !/place/.test(t)) return 52;
    const pl = t.match(/(\d+)\D*place/); if (pl) return 900 + parseInt(pl[1], 10);
    return 100;
  };
  const roundTitle = (r) => {
    const k = String(r || '').toLowerCase();
    if (k === 'quarter-final') return 'Ketvirtfinaliai';
    if (k === 'semi-final') return 'Pusfinaliai';
    if (k === 'final') return 'Finalas';
    const pl = String(r || '').match(/(\d+)\D*place/i); if (pl) return `Dėl ${pl[1]} vietos`;
    return String(r || '');
  };
  const toMatch = (m) => {
    const sc = parseScore(m.score);
    return {
      team1: (m.team1 || []).join(' / '), team2: (m.team2 || []).join(' / '),
      sets1: sc.sets.map((s) => s[0]).join(' '), sets2: sc.sets.map((s) => s[1]).join(' '),
      winner: m.winner, court: m.court || null, time: m.time || null,
    };
  };

  const bySeg = new Map();
  for (const m of inCat) { if (!bySeg.has(m.segment)) bySeg.set(m.segment, []); bySeg.get(m.segment).push(m); }
  const order = [...bySeg.keys()].sort((a, b) => (a === 'main' ? -1 : b === 'main' ? 1 : String(a).localeCompare(String(b))));

  const segments = [];
  for (const s of order) {
    const byRound = new Map();
    for (const m of bySeg.get(s)) { const r = m.round || ''; if (!byRound.has(r)) byRound.set(r, []); byRound.get(r).push(m); }
    const roundKeys = [...byRound.keys()].sort((a, b) => roundRank(a) - roundRank(b));

    let third = null;
    const rounds = [];
    for (const rk of roundKeys) {
      if (s === 'main' && /3rd|3\D*place/i.test(rk)) {
        const arr = byRound.get(rk); if (arr.length) third = toMatch(arr[0]);
        continue;
      }
      rounds.push({ title: roundTitle(rk), matches: byRound.get(rk).map(toMatch) });
    }
    if (!rounds.length && !third) continue;
    segments.push({
      key: `${categoryId}-${s}`, label: s === 'main' ? 'Pagrindinis' : String(s),
      is_main: s === 'main', rounds, third, placements: [],
    });
  }
  return segments;
}

async function fetchMatches(tournamentId) {
  const data = await gql(`{
    matches(filter: { tournament: ${tournamentId} }) {
      id time date duration status isMatchInProgress firstScoreSubmittedAt round bracketType score
      court { id name }
      tournamentCategory { id category { name } }
      group { id name }
      entry1 { id } entry2 { id } winner { id }
      participant1 { users { name surname } }
      participant2 { users { name surname } }
    }
  }`);
  return (data.matches || []).map(normalizeMatch);
}

// ── Bracket ištraukimas iš draw objekto ────────────────────
function extractBracket(draw) {
  const rounds = draw.rounds || [];
  if (!rounds.length) return null;

  const titleByCount = { 16: '1/16 finalis', 8: '1/8 finalis', 4: 'Ketvirtfinaliai', 2: 'Pusfinaliai', 1: 'Finalas' };
  const pairName = (t) => (t.users || [])
    .map((u) => `${u.user?.name || ''} ${u.user?.surname || ''}`.trim()).filter(Boolean).join(' / ');
  const parseSets = (score) => {
    const s1 = [], s2 = [];
    (score || '').trim().split(/\s+/).filter(Boolean).forEach((tok) => {
      const parts = tok.replace(/[\[\]]/g, '').split(':');
      if (parts.length === 2) { s1.push(parts[0]); s2.push(parts[1]); }
    });
    return [s1.join(' '), s2.join(' ')];
  };
  const matchOf = (seed) => {
    const teams = seed.teams || [];
    const [sets1, sets2] = parseSets(seed.addScore && seed.addScore.addScore);
    let winner = null;
    if (seed.winner && teams[0] && seed.winner.id === teams[0].id) winner = 1;
    else if (seed.winner && teams[1] && seed.winner.id === teams[1].id) winner = 2;
    const court = (seed.court && seed.court.name) || (typeof seed.court === 'string' ? seed.court : null);
    return {
      team1: teams[0] ? pairName(teams[0]) : '',
      team2: teams[1] ? pairName(teams[1]) : '',
      sets1, sets2, winner,
      court: court || null,
      time: seed.time || null,
    };
  };

  const main = [];
  let expected = (rounds[0].seeds || []).length;
  for (const r of rounds) {
    const c = (r.seeds || []).length;
    if (c === expected) { main.push(r); if (c === 1) break; expected = c / 2; }
    else break;
  }
  const outRounds = main.map((r) => ({
    title: titleByCount[(r.seeds || []).length] || r.title || '',
    matches: (r.seeds || []).map(matchOf),
  }));

  const thirdRound = rounds.find((r) => /3rd/i.test(r.title || '') && (r.seeds || []).length === 1);
  let third = thirdRound ? matchOf(thirdRound.seeds[0]) : null;
  // Some formats (double-elimination) keep the 3rd-place match in a separate
  // `thirdPlaceRound` field instead of in `rounds`.
  if (!third && draw.thirdPlaceRound) {
    const tp = draw.thirdPlaceRound;
    const seed = Array.isArray(tp.seeds) ? tp.seeds[0] : (tp && tp.teams ? tp : null);
    if (seed) third = matchOf(seed);
  }

  // Placement brackets: leftover rounds (not main, not 3rd place) grouped into
  // blocks, each ending in a "Nth place" round. The block's place range is the
  // segment label Tournated shows (5-8, 9-16, 13-16, …): the closing round is
  // "(start+2)th place" and the block holds 2×(first-round matches) teams.
  const mainSet = new Set(main);
  const placements = [];
  let acc = [];
  for (const r of rounds) {
    if (mainSet.has(r) || r === thirdRound) continue;
    acc.push({ title: r.title || '', matches: (r.seeds || []).map(matchOf) });
    const m = (r.title || '').match(/(\d+)\D*place/i);
    if (m) {
      const named = parseInt(m[1], 10);
      const firstSeeds = (acc[0] && acc[0].matches.length) || 1;
      const start = named - 2;
      const end = start + 2 * firstSeeds - 1;
      const label = (start > 0 && end >= start)
        ? (start === end ? `Dėl ${start} vietos` : `${start}-${end}`)
        : `Dėl ${named} vietos`;

      // The winners' final (round before the "Nth place" round) decides the
      // top place of the block; the "Nth place" round the lower one. When both
      // are single matches, merge them into one final column, labelling each by
      // the place it decides (Tournated shows them side by side).
      const placeFinal = acc[acc.length - 1];
      const winnersFinal = acc.length >= 2 ? acc[acc.length - 2] : null;
      const canMerge = start > 0
        && placeFinal.matches.length === 1
        && winnersFinal && winnersFinal.matches.length === 1;

      let displayRounds;
      if (canMerge) {
        displayRounds = acc.slice(0, acc.length - 2).map((rr) => ({ title: rr.title, matches: rr.matches }));
        displayRounds.push({ title: '', matches: [
          { ...winnersFinal.matches[0], place: `Dėl ${start} vietos` },
          { ...placeFinal.matches[0], place: `Dėl ${named} vietos` },
        ] });
      } else {
        // Fallback: keep columns, just translate the "Nth place" header.
        displayRounds = acc.map((rr, i) => ({
          title: i === acc.length - 1 ? `Dėl ${named} vietos` : rr.title,
          matches: rr.matches,
        }));
      }

      placements.push({ key: `place-${named}`, title: label, rounds: displayRounds });
      acc = [];
    }
  }

  // Consolation rounds with no "Nth place" title (e.g. double-elimination R1/R2
  // that decide 5-8). Group the leftovers into one segment, labelled from size.
  if (placements.length === 0 && acc.length) {
    const size = Number(draw.size) || 0;
    const start = size ? Math.floor(size / 2) + 1 : 0;
    const label = start > 1 ? `${start}-${size}` : 'Paguodos';
    placements.push({ key: 'consolation', title: label, rounds: acc });
  }

  return { rounds: outRounds, third, placements };
}

// ── Kurių turnyrų reikia (iš serverio overlay'ų) ────────────
async function fetchWantedTournaments() {
  const res = await fetch(`${SITE_URL}/overlay/wanted`, {
    headers: { 'X-Overlay-Token': INGEST_TOKEN },
  });
  if (!res.ok) throw new Error(`Serveris atsakė ${res.status}`);
  const json = await res.json();
  return Array.isArray(json.tournament_ids) ? json.tournament_ids.map(String) : [];
}

// Paskutinė sėkmingai gauta turnyro info — kad laikinai nulūžus Tournated
// „tournament" užklausai overlay'ai toliau gautų susitikimus/grupes.
const lastGoodTournament = new Map();

// Kešas: kurios per-kategorijos užklausos užrakintos (groups/draws/participants).
// Greitieji ciklai jų nebekartoja — statome viską iš „matches". Kas
// RECHECK_EVERY ciklų perpatikrinam, ar Tournated atrakino.
const gateCache = new Map();
const RECHECK_EVERY = 15;
let cycleN = 0;

// „Sunkūs" duomenys (kategorijos, grupės, bracketai, dalyviai) keičiasi lėtai,
// tad juos perskaičiuojam rečiau — kas FULL_EVERY ciklų. Rungtynės (grafikas,
// rezultatai) atnaujinamos KIEKVIENĄ ciklą, kad matytųsi greitai.
const heavyCache = new Map();
const FULL_EVERY = Number(process.env.FULL_EVERY || 2); // sunkūs duomenys ~kas 2 ciklus (≈60 s)
let tournamentBroken = false; // ar „tournament(id:)" šiuo metu neveikia (skip'inam)
let heavyRefreshing = false;  // ar šiuo metu fone atnaujinami „sunkūs" duomenys

/**
 * Atsarginis kategorijų šaltinis. Tournated „tournament(id:)" užklausa gali
 * kaboti (jų pusės gedimas), o „tournamentDrawCategories" veikia ir grąžina
 * tą pačią kategorijų struktūrą.
 */
async function fetchDrawCategories(tournamentId) {
  const data = await gql(`{ tournamentDrawCategories(filter: { tournament: ${tournamentId} }) }`);
  return (data.tournamentDrawCategories || []).map((c) => ({
    id: c.id,
    mde: c.mde ?? null,
    category: c.category ? { id: c.category.id, name: c.category.name } : null,
  }));
}

// ── „Sunkūs" duomenys: kategorijos, grupės, bracketai, dalyviai ──
async function computeHeavy(tournamentId, key, matches) {
  let tournament = null;
  // „tournament(id:)" kabo — bandome tik pirmą kartą ir retkarčiais (recheck),
  // kad neblokuotų kiekvieno sunkaus ciklo.
  if (!tournamentBroken || (cycleN % RECHECK_EVERY === 0)) {
    try {
      tournament = await fetchTournament(tournamentId);
      tournamentBroken = false;
    } catch (e) {
      tournamentBroken = true;
    }
  }

  let haveTitle = true;
  let categories = [];

  if (tournament) {
    lastGoodTournament.set(key, tournament);
    categories = tournament.tournamentCategory || [];
  } else if (lastGoodTournament.has(key)) {
    tournament = lastGoodTournament.get(key);
    categories = tournament.tournamentCategory || [];
    console.error('  ↩︎ Naudoju paskutinę žinomą turnyro info');
  } else {
    tournament = { title: null, tournamentCategory: [] };
    haveTitle = false;
    try {
      categories = await fetchDrawCategories(tournamentId);
    } catch (e) {
      console.error(`  ! Kategorijų kelias nepavyko: ${e.message}`);
    }
  }

  // Papildome kategorijas tomis, kurios randamos tik susitikimuose (grupinės).
  {
    const have = new Set(categories.map((c) => String(c.id)));
    const seen = new Map();
    for (const m of matches) {
      const cid = m.category_id;
      if (cid != null && !have.has(String(cid)) && !seen.has(String(cid))) {
        seen.set(String(cid), { id: cid, mde: null, category: { id: cid, name: m.category || ('#' + cid) } });
      }
    }
    if (seen.size) {
      categories = categories.concat([...seen.values()]);
      console.error(`  ↩︎ Kategorijos papildytos iš matches (+${seen.size}); iš viso ${categories.length}`);
    }
  }

  const groupsByCategory = {};
  const participantsByCategory = {};

  const recheck = (cycleN % RECHECK_EVERY) === 0; // kartais perpatikrinam ar atrakino
  for (const cat of categories) {
    const g = gateCache.get(String(cat.id)) || {};

    let groups = [];
    if (!g.groups || recheck) {
      try { groups = await fetchGroups(cat.id); } catch (_) { groups = []; }
    }
    if (!groups.length) {
      groups = buildGroupsFromMatches(matches, cat.id);
      g.groups = true; // legacy neveikia — kituose cikluose praleisim
    } else {
      g.groups = false;
    }
    groupsByCategory[String(cat.id)] = groups;

    let parts = [];
    if (!g.participants || recheck) {
      try { parts = await fetchParticipants(tournamentId, cat.id); g.participants = false; }
      catch (_) { g.participants = true; }
    }
    if (!parts.length) parts = buildParticipantsFromMatches(matches, cat.id); // atsarginis kelias iš rungtynių
    participantsByCategory[String(cat.id)] = parts;

    gateCache.set(String(cat.id), g);
  }

  const categoryStages = {};
  const bracketsByCategory = {};
  for (const cat of categories) {
    const groups = groupsByCategory[String(cat.id)] || [];
    const g = gateCache.get(String(cat.id)) || {};
    let draws = [];
    if (!g.draws || recheck) {
      try { draws = await fetchDraws(cat.id); } catch (_) { draws = []; }
    }

    // Selectable "segments". A play-each-place draw is split into its main tree
    // plus one segment per placement block (5-8, 9-16, …). Separate draws (one
    // per place) each become a single segment labelled by their title.
    const segments = [];
    for (const draw of draws) {
      const b = extractBracket(draw);
      if (!b || !b.rounds.length) continue;
      if (b.placements && b.placements.length) {
        segments.push({
          key: `${draw.id}-main`, label: 'Pagrindinis', is_main: true,
          rounds: b.rounds, third: b.third, placements: [],
        });
        for (const p of b.placements) {
          segments.push({
            key: `${draw.id}-${p.key}`, label: p.title, is_main: false,
            rounds: p.rounds, third: null, placements: [],
          });
        }
      } else {
        segments.push({
          key: String(draw.id), label: draw.title || '', is_main: false,
          rounds: b.rounds, third: b.third, placements: [],
        });
      }
    }

    // Nepavykus per „draws" — atkuriame bracketus iš matches.
    if (!segments.length) {
      const rebuilt = buildBracketsFromMatches(matches, cat.id);
      if (rebuilt.length) segments.push(...rebuilt);
      g.draws = true; // legacy neveikia — kituose cikluose praleisim
    } else {
      g.draws = false;
    }
    gateCache.set(String(cat.id), g);

    categoryStages[String(cat.id)] = {
      has_groups: groups.length > 0,
      has_bracket: segments.length > 0,
      draw_type: draws[0]?.type ?? null,
      draw_size: draws[0]?.size ?? cat.mde ?? null,
    };
    if (segments.length) bracketsByCategory[String(cat.id)] = { segments };
  }

  return {
    categories, groupsByCategory, participantsByCategory, categoryStages, bracketsByCategory,
    haveTitle, title: tournament.title || null,
  };
}

// ── Vienas ciklas: surinkti viską ir nusiųsti ───────────────
async function pushOnce(tournamentId) {
  cycleN++;
  const key = String(tournamentId);

  // Rungtynes imame KIEKVIENĄ ciklą (grafikas/rezultatai — greitai).
  let matches = [];
  try { matches = await fetchMatches(tournamentId); } catch (e) { console.error(`  ! Matches: ${e.message}`); }

  // Siunčiam iškart: šviežios rungtynės + paskutiniai kešuoti „sunkūs" duomenys.
  const heavy = heavyCache.get(key);
  const snapshot = { tournament_id: tournamentId, matches };
  if (heavy) {
    snapshot.categories = heavy.categories;
    snapshot.groups_by_category = heavy.groupsByCategory;
    snapshot.participants_by_category = heavy.participantsByCategory;
    snapshot.category_stages = heavy.categoryStages;
    snapshot.brackets_by_category = heavy.bracketsByCategory;
    if (heavy.haveTitle) snapshot.title = heavy.title || null;
    else snapshot.partial = true;
  } else {
    snapshot.partial = true; // sunkių dar neturim — siunčiam tik grafiką
  }

  const res = await fetch(`${SITE_URL}/overlay/ingest`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Overlay-Token': INGEST_TOKEN,
    },
    body: JSON.stringify(snapshot),
  });

  if (!res.ok) {
    const text = await res.text();
    throw new Error(`Serveris atsakė ${res.status}: ${text.slice(0, 200)}`);
  }

  const catCount = (snapshot.categories || []).length;
  const groupCount = Object.values(snapshot.groups_by_category || {}).reduce((n, g) => n + g.length, 0);
  const titleLabel = snapshot.title ? `"${snapshot.title}"` : (heavy ? '(pavadinimas — ankstesnis)' : '(kraunama…)');
  console.log(`✅ [${new Date().toLocaleTimeString()}] Nusiųsta: ${titleLabel} — ${catCount} kat., ${groupCount} grupių, ${matches.length} susitikimų`);

  // „Sunkius" duomenis (grupes/bracketus/dalyvius) atnaujinam FONE — neblokuoja
  // grafiko. Kai baigia, atsiranda kešе kitiems ciklams.
  const refreshHeavy = !heavyCache.has(key) || (cycleN % FULL_EVERY === 0);
  if (refreshHeavy && !heavyRefreshing) {
    heavyRefreshing = true;
    computeHeavy(tournamentId, key, matches)
      .then((h) => { heavyCache.set(key, h); })
      .catch((e) => console.error(`  ! Sunkių duomenų atnaujinimas: ${e.message}`))
      .finally(() => { heavyRefreshing = false; });
  }
}

// ── Pagrindinis ciklas ──────────────────────────────────────
async function loop() {
  console.log(`🏓 Overlay push paleistas`);
  console.log(`   Turnyrai: iš admin (auto)${TOURNAMENT_ID ? ` arba ${TOURNAMENT_ID}` : ''}`);
  console.log(`   Svetainė: ${SITE_URL}`);
  console.log(`   Grafikas/rezultatai: kas ${POLL_INTERVAL_MS / 1000}s | grupės/bracketai/dalyviai: kas ~${(POLL_INTERVAL_MS * FULL_EVERY) / 1000}s`);
  if (TOURNATED_TOKEN) {
    let exp = '';
    try { const pl = JSON.parse(Buffer.from(TOURNATED_TOKEN.split('.')[1], 'base64').toString()); if (pl.exp) exp = ` (galioja iki ${new Date(pl.exp * 1000).toLocaleString()})`; } catch (_) {}
    console.log(`   Tournated tokenas: ✔ prijungtas${exp}`);
  } else {
    console.log(`   Tournated tokenas: ✗ nėra (dirbama iš rungtynių)`);
  }
  console.log('');

  if (INGEST_TOKEN === 'ĮRAŠYK_SLAPTĄ_RAKTĄ') {
    console.error('❌ Pirma įrašyk INGEST_TOKEN (tą patį, kaip serverio .env OVERLAY_INGEST_TOKEN).');
    process.exit(1);
  }

  for (;;) {
    try {
      let ids = [];
      try {
        ids = await fetchWantedTournaments();
      } catch (e) {
        console.error(`⚠️  Nepavyko gauti turnyrų sąrašo iš serverio: ${e.message}`);
      }
      // Atsarginis variantas, jei serveris negrąžino nieko.
      if (!ids.length && TOURNAMENT_ID) ids = [TOURNAMENT_ID];

      if (!ids.length) {
        console.error('⚠️  Nėra nei vieno turnyro (admin\'e nepriskirtas turnyro ID).');
      }

      for (const id of ids) {
        try {
          await pushOnce(id);
        } catch (e) {
          console.error(`❌ Turnyras ${id}: ${e.message}`);
        }
      }
    } catch (e) {
      console.error(`❌ Klaida: ${e.message}`);
    }
    await new Promise((r) => setTimeout(r, POLL_INTERVAL_MS));
  }
}

loop();
