// ============================================================
//  Padelioturnyrai.lt – "Grafikas" (viešo tvarkaraščio) push scenarijus
// ------------------------------------------------------------
//  Paleidžiamas TAVO kompiuteryje (serveris negali pasiekti Tournated
//  — žr. docs/overlays.md). Kas POLL_INTERVAL_MS nuskaito VISĄ turnyro
//  mačų sąrašą VIENU užklausimu per viešą play.padel.lt GraphQL (tą
//  patį, kurį naudoja pati Tournated svetainė savo "Order of play"
//  puslapyje — https://play.padel.lt/tournament/{id}/order_of_play),
//  ir nusiunčia į svetainę (POST /grafikas/ingest).
//
//  2026-09-12 (turnyro dieną) persėsta nuo oficialaus /api/v2 REST —
//  tas API buvo nepatikimas šiam turnyrui (502 daugiau nei 1 mačui
//  viename atsakyme, žr. git istoriją) ir dėl to vienas ciklas
//  užtrukdavo 5-8 min bei praleisdavo rezultatus. Šis GraphQL
//  endpoint'as: (a) grąžina VISUS mačus VIENU užklausimu, be
//  puslapiavimo; (b) NEREIKALAUJA API rakto (viešas, kaip ir pati
//  svetainė); (c) jau turi lygio pavadinimą (`name`, pvz. "Moterys
//  B 1") tiesiai laukelyje — nebereikia Excel entry_lists susiejimo.
//
//  Reikia: Node.js 18+ (turi įmontuotą fetch).
//  Paleidimas:
//      TOURNAMENT_ID=11532 INGEST_TOKEN=xxxx node schedule-push.js
//  arba įrašius tokeną į tools/overlay-push/.ingest-token:
//      TOURNAMENT_ID=11532 node schedule-push.js
// ============================================================

import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const SITE_URL      = process.env.SITE_URL      || 'https://padelioturnyrai.lt';
const TOURNAMENT_ID  = process.env.TOURNAMENT_ID || '';
const POLL_INTERVAL_MS = Number(process.env.POLL_INTERVAL_MS || 180000);

const GRAPHQL_URL = 'https://play.padel.lt/api/graphql';

const IS_COMPILED = !/[\\/](node|bun)(\.exe)?$/i.test(process.execPath || '');
const secretDir = (() => {
  try { return IS_COMPILED ? dirname(process.execPath) : dirname(fileURLToPath(import.meta.url)); }
  catch (_) { return '.'; }
})();
const INGEST_TOKEN_FILE = join(secretDir, '.ingest-token');

function readSecretFile(path) {
  try { if (existsSync(path)) return readFileSync(path, 'utf8').trim(); } catch (_) {}
  return '';
}

const INGEST_TOKEN = process.env.INGEST_TOKEN || readSecretFile(INGEST_TOKEN_FILE);

if (!TOURNAMENT_ID) {
  console.error('❌ Reikia TOURNAMENT_ID (Tournated turnyro ID, pvz. 11532).');
  process.exit(1);
}
if (!INGEST_TOKEN) {
  console.error(`❌ Reikia ingest tokeno (turi sutapti su serverio .env OVERLAY_INGEST_TOKEN). Nustatyk INGEST_TOKEN arba įrašyk į ${INGEST_TOKEN_FILE}`);
  process.exit(1);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const MATCHES_QUERY = `query tournamentAllMatches($filter: ListMatchesInput) {
  tournamentAllMatches: tournamentAllMatchesPublic(filter: $filter) {
    matchesArray {
      court
      matches {
        id
        date
        time
        status
        teamScore
        name
        isBye
        isWalkover
        isDisqualified
        isMatchInProgress
        isMatchTie
        court { id name }
        group { id name segment }
        team1Entry { user { name surname } team { id title image } }
        team2Entry { user { name surname } team { id title image } }
      }
    }
  }
}`;

async function fetchAllMatches(tournamentId, timeoutMs = 20000) {
  const ac = new AbortController();
  const timer = setTimeout(() => ac.abort(), timeoutMs);
  let res;
  try {
    res = await fetch(GRAPHQL_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        operationName: 'tournamentAllMatches',
        variables: { filter: { tournament: Number(tournamentId) } },
        query: MATCHES_QUERY,
      }),
      signal: ac.signal,
    });
  } catch (e) {
    if (e.name === 'AbortError') throw new Error(`GraphQL neatsakė per ${timeoutMs / 1000}s`);
    throw new Error(`Tinklo klaida: ${e.message}`);
  } finally {
    clearTimeout(timer);
  }
  const text = await res.text();
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${text.slice(0, 200)}`);
  let json;
  try { json = JSON.parse(text); } catch { throw new Error(`Ne JSON atsakymas: ${text.slice(0, 150)}`); }
  if (json.errors) throw new Error(`GraphQL klaida: ${JSON.stringify(json.errors).slice(0, 300)}`);

  const groups = json.data?.tournamentAllMatches?.matchesArray || [];
  const raw = groups.flatMap((g) => g.matches || []);
  return raw.map(normaliseMatch);
}

// "6:3 6:1" arba "7:5 6:7 [10:5]" -> [{side1,side2}, ...]
function parseTeamScore(ts) {
  if (!ts) return [];
  return ts.trim().split(/\s+/).filter(Boolean).map((tok) => {
    const [a, b] = tok.replace(/[[\]]/g, '').split(':').map((n) => parseInt(n, 10));
    return { side1: a, side2: b };
  }).filter((s) => !isNaN(s.side1) && !isNaN(s.side2));
}

function winnerFromSets(sets) {
  if (!sets.length) return null;
  let w1 = 0, w2 = 0;
  sets.forEach((s) => { if (s.side1 > s.side2) w1++; else if (s.side2 > s.side1) w2++; });
  if (w1 === w2) return null;
  return w1 > w2 ? 1 : 2;
}

function entryToTeam(entry) {
  const team = entry && entry[0] && entry[0].team;
  return team ? { team_id: team.id, title: team.title, image: team.image || null } : null;
}

function entryToParticipants(entry, side) {
  return (entry || []).map((e) => ({ side, name: e.user?.name || '', surname: e.user?.surname || '' }));
}

// Perkelia GraphQL formą į tą pačią vidinę formą, kurią jau naudoja
// ScheduleController/schedule.blade.php (match_id, court.name, team1/team2,
// participants, sets[{side1,side2}], division, ...).
function normaliseMatch(m) {
  const sets = m.status === 'completed' ? parseTeamScore(m.teamScore) : [];
  return {
    match_id: m.id,
    date: m.date ? String(m.date).slice(0, 10) : null,
    time: m.time || null,
    court: m.court ? { court_id: m.court.id, name: m.court.name } : null,
    division: m.name || null,
    team1: entryToTeam(m.team1Entry),
    team2: entryToTeam(m.team2Entry),
    participants: [...entryToParticipants(m.team1Entry, 1), ...entryToParticipants(m.team2Entry, 2)],
    sets,
    winner_side: winnerFromSets(sets),
    is_bye: !!m.isBye,
    is_walkover: !!m.isWalkover,
    is_disqualified: !!m.isDisqualified,
    is_match_tie: !!m.isMatchTie,
    is_match_in_progress: !!m.isMatchInProgress,
    updated_at: new Date().toISOString(),
    _group: m.group ? m.group.name : null,
  };
}

async function pushSnapshot(tournamentId, matches) {
  const groupName = matches.find((m) => m._group)?._group || 'Bendra';
  const clean = matches.map(({ _group, ...rest }) => rest);
  const res = await fetch(`${SITE_URL}/grafikas/ingest`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Overlay-Token': INGEST_TOKEN },
    body: JSON.stringify({
      tournament_id: String(tournamentId),
      matches: clean,
      groups: [{ name: groupName }],
      standings: [],
    }),
  });
  if (!res.ok) throw new Error(`Ingest atmetė: HTTP ${res.status} — ${(await res.text()).slice(0, 200)}`);
}

async function cycle() {
  const started = Date.now();
  const matches = await fetchAllMatches(TOURNAMENT_ID);
  await pushSnapshot(TOURNAMENT_ID, matches);
  const played = matches.filter((m) => m.sets.length || m.winner_side).length;
  const secs = ((Date.now() - started) / 1000).toFixed(1);
  console.log(`✅ [${new Date().toLocaleTimeString()}] ${matches.length} mačų (${played} sužaista) — ${secs}s`);
}

const RUN_ONCE = process.env.ONCE === '1' || process.argv.includes('--once');

async function main() {
  console.log('📅 Grafikas push paleistas (play.padel.lt GraphQL, be API rakto)');
  console.log(`   Turnyras: ${TOURNAMENT_ID}`);
  console.log(`   Svetainė: ${SITE_URL}`);
  if (RUN_ONCE) {
    console.log('   Režimas: vienas ciklas (ONCE)\n');
    try { await cycle(); } catch (e) { console.error(`⚠️  Klaida: ${e.message}`); process.exit(1); }
    return;
  }

  console.log(`   Kas ${POLL_INTERVAL_MS / 1000}s\n`);
  for (;;) {
    try {
      await cycle();
    } catch (e) {
      console.error(`⚠️  [${new Date().toLocaleTimeString()}] Klaida: ${e.message}`);
    }
    await sleep(POLL_INTERVAL_MS);
  }
}

main();
