// ============================================================
//  Padelioturnyrai.lt – "Grafikas" (viešo tvarkaraščio) push scenarijus
// ------------------------------------------------------------
//  Paleidžiamas TAVO kompiuteryje (serveris negali pasiekti
//  api.tournated.com — žr. docs/overlays.md). Kas POLL_INTERVAL_MS
//  perskaito visą turnyro mačų sąrašą (su korto/laiko/rezultato
//  duomenimis) per naują oficialų Tournated /api/v2, ir nusiunčia į
//  svetainę (POST /grafikas/ingest), iš kur juos rodo viešas
//  /grafikas/{turnyras} puslapis.
//
//  Reikia: Node.js 18+ (turi įmontuotą fetch).
//
//  Jokia paslaptis (API raktas, ingest tokenas) NIEKADA nerašoma į šį failą
//  (žr. atmintį apie .claude/settings.local.json nutekėjimą) — abu laikomi
//  arba env kintamuosiuose, arba vietiniuose failuose (į git nepatenka).
//
//  Paleidimas:
//      TOURNAMENT_ID=11532 TOURNATED_API_KEY=xxxx INGEST_TOKEN=yyyy node schedule-push.js
//  arba parašius raktus į tools/overlay-push/.api-key ir .ingest-token:
//      TOURNAMENT_ID=11532 node schedule-push.js
// ============================================================

import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const SITE_URL     = process.env.SITE_URL     || 'https://padelioturnyrai.lt';
const TOURNAMENT_ID = process.env.TOURNAMENT_ID || '';
const POLL_INTERVAL_MS = Number(process.env.POLL_INTERVAL_MS || 90000);

const API_BASE = 'https://api.tournated.com/api/v2';

const IS_COMPILED = !/[\\/](node|bun)(\.exe)?$/i.test(process.execPath || '');
const secretDir = (() => {
  try { return IS_COMPILED ? dirname(process.execPath) : dirname(fileURLToPath(import.meta.url)); }
  catch (_) { return '.'; }
})();
const KEY_FILE = join(secretDir, '.api-key');
const INGEST_TOKEN_FILE = join(secretDir, '.ingest-token');

function readSecretFile(path) {
  try { if (existsSync(path)) return readFileSync(path, 'utf8').trim(); } catch (_) {}
  return '';
}

const API_KEY = process.env.TOURNATED_API_KEY || readSecretFile(KEY_FILE);
const INGEST_TOKEN = process.env.INGEST_TOKEN || readSecretFile(INGEST_TOKEN_FILE);

if (!TOURNAMENT_ID) {
  console.error('❌ Reikia TOURNAMENT_ID (Tournated turnyro ID, pvz. 11532).');
  process.exit(1);
}
if (!API_KEY) {
  console.error(`❌ Reikia Tournated API rakto. Nustatyk TOURNATED_API_KEY arba įrašyk į ${KEY_FILE}`);
  process.exit(1);
}
if (!INGEST_TOKEN) {
  console.error(`❌ Reikia ingest tokeno (turi sutapti su serverio .env OVERLAY_INGEST_TOKEN). Nustatyk INGEST_TOKEN arba įrašyk į ${INGEST_TOKEN_FILE}`);
  process.exit(1);
}

// ── REST pagalbinė ──────────────────────────────────────────
async function api(path, timeoutMs = 15000) {
  const ac = new AbortController();
  const timer = setTimeout(() => ac.abort(), timeoutMs);
  let res;
  try {
    res = await fetch(`${API_BASE}${path}`, {
      headers: { 'x-api-key': API_KEY },
      signal: ac.signal,
    });
  } catch (e) {
    if (e.name === 'AbortError') throw new Error(`Tournated API neatsakė per ${timeoutMs / 1000}s`);
    throw new Error(`Tinklo klaida: ${e.message}`);
  } finally {
    clearTimeout(timer);
  }
  const text = await res.text();
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${text.slice(0, 200)}`);
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`Ne JSON atsakymas (HTTP ${res.status}): ${text.slice(0, 120)}`);
  }
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function apiWithRetry(path, attempts = 3) {
  let lastErr;
  for (let i = 0; i < attempts; i++) {
    try {
      return await api(path);
    } catch (e) {
      lastErr = e;
      if (i < attempts - 1) await sleep(1500 * Math.pow(2, i)); // 1.5s, 3s, 6s
    }
  }
  throw lastErr;
}

// Šio turnyro `/matches` yra nestabilus Tournated pusėje: bet koks atsakymas
// su >1 mačo pilnu įrašu dažnai grąžina 502 (matyt dėl didelių side_a/side_b
// sąrašų). Todėl einame po vieną mačą su cursor puslapiavimu ir kartojimais;
// jei konkretus mačas ir toliau nepasiekiamas po bandymų, jį praleidžiame
// šiame cikle (liks paskutinė žinoma jo būsena) — vienas blogas mačas
// nesugriauna viso siuntimo.
async function fetchAllMatchesFull(tournamentId, onProgress) {
  const matches = [];
  const failed = [];
  let cursor = null;
  let guard = 0;
  while (guard++ < 500) {
    const qs = new URLSearchParams({ tournamentId, view: 'full', limit: '1' });
    if (cursor) qs.set('cursor', cursor);
    let json;
    try {
      json = await apiWithRetry(`/matches?${qs.toString()}`);
    } catch (e) {
      failed.push({ cursor, error: e.message });
      // Be sėkmingo atsakymo nežinome kito cursor — negalime tęsti toliau
      // šiame bandyme. Sustojame; kitas ciklas pradės iš naujo nuo pradžių.
      break;
    }
    const row = (json.data || [])[0];
    if (row) matches.push(row);
    cursor = json.meta?.next_cursor || null;
    if (onProgress) onProgress(matches.length, json.meta?.total || null);
    if (!cursor) break;
    await sleep(150); // švelniai, kad netrenktume į 100 req/60s limitą
  }
  return { matches, failed };
}

async function fetchTournament(tournamentId) {
  const json = await api(`/tournaments/${tournamentId}`);
  return {
    id: json.tournament_id,
    name: json.tournament_name,
    date: (json.tournament_start_date || '').slice(0, 10),
    tz: json.tournament_tz,
    court_amount: json.court_amount,
  };
}

async function fetchGroups(tournamentId) {
  const json = await api(`/groups?${new URLSearchParams({ tournamentId, include: 'entries,matches' })}`);
  return json.data || [];
}

async function pushSnapshot(tournamentId, { tournament, matches, groups }) {
  const res = await fetch(`${SITE_URL}/grafikas/ingest`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Overlay-Token': INGEST_TOKEN },
    body: JSON.stringify({ tournament_id: String(tournamentId), tournament, matches, groups, standings: [] }),
  });
  if (!res.ok) throw new Error(`Ingest atmetė: HTTP ${res.status} — ${(await res.text()).slice(0, 200)}`);
}

// Paskutiniai žinomi mačai (match_id => match). Dalinis ciklas (kai kai
// kurie mačai nepasiekiami) papildo/atnaujina šį cache'ą, o ne perrašo jį
// tuščiu sąrašu — vienas nepavykęs mačas nedingsta iš viešo puslapio.
const knownMatches = new Map();

async function cycle() {
  const tournament = await fetchTournament(TOURNAMENT_ID);

  const started = Date.now();
  const { matches, failed } = await fetchAllMatchesFull(TOURNAMENT_ID, (n, total) => {
    if (n % 10 === 0) console.log(`  … ${n}${total ? '/' + total : ''} mačų (${((Date.now() - started) / 1000).toFixed(0)}s)`);
  });
  for (const m of matches) knownMatches.set(m.match_id, m);
  if (failed.length) {
    console.log(`  ↩︎ ${failed.length} mačo(-ų) šiame cikle nepavyko gauti — liks ankstesnė žinoma būsena`);
  }

  let groups = [];
  try {
    groups = await fetchGroups(TOURNAMENT_ID);
  } catch (e) {
    console.log(`  ↩︎ Grupių nepavyko gauti (${e.message}) — siunčiu be jų`);
  }

  const allMatches = Array.from(knownMatches.values());
  await pushSnapshot(TOURNAMENT_ID, { tournament, matches: allMatches, groups });
  const played = allMatches.filter((m) => (m.sets && m.sets.length) || m.winner_side).length;
  const secs = ((Date.now() - started) / 1000).toFixed(0);
  console.log(`✅ [${new Date().toLocaleTimeString()}] ${tournament.name} — ${allMatches.length} mačų (${played} sužaista), ${groups.length} grupių — ciklas truko ${secs}s`);
}

// ONCE=1 (arba --once) — vienas ciklas ir išeina, exit code 1 jei nepavyko.
// Naudojama GitHub Actions cron workflow'e (.github/workflows/schedule-push.yml),
// kur kiekvienas paleidimas yra švarus, be ilgai veikiančio proceso — nereikia
// palikti jokio kompiuterio įjungto.
const RUN_ONCE = process.env.ONCE === '1' || process.argv.includes('--once');

async function main() {
  console.log('📅 Grafikas push paleistas');
  console.log(`   Turnyras: ${TOURNAMENT_ID}`);
  console.log(`   Svetainė: ${SITE_URL}`);
  if (RUN_ONCE) {
    console.log('   Režimas: vienas ciklas (ONCE)\n');
    try {
      await cycle();
    } catch (e) {
      console.error(`⚠️  Klaida: ${e.message}`);
      process.exit(1);
    }
    return;
  }

  console.log(`   Tarpas tarp ciklų: ${POLL_INTERVAL_MS / 1000}s (kiekvienas ciklas pats gali užtrukti kelias minutes — API pusėje kiekvieno mačo pilnas įrašas imamas atskirai, nes Tournated /matches?view=full nestabilus daugiau nei 1 mačui vienu atsakymu)\n`);
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
