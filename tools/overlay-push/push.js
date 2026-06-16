// ============================================================
//  Padelioturnyrai.lt – Overlay duomenų "push" scenarijus
// ------------------------------------------------------------
//  Paleidžiamas TAVO kompiuteryje (jis turi internetą, serveris ne).
//  Kas POLL_INTERVAL_MS nuskaito Tournated turnyro duomenis ir
//  nusiunčia juos į tavo svetainę (POST /overlay/ingest).
//
//  Reikia: Node.js 18+ (turi įmontuotą fetch).
//  Paleidimas:
//      node push.js
//  arba su aplinkos kintamaisiais:
//      TOURNAMENT_ID=10424 INGEST_TOKEN=xxxx node push.js
// ============================================================

// ── Nustatymai (gali keisti čia arba per aplinkos kintamuosius) ──
const SITE_URL       = process.env.SITE_URL       || 'https://padelioturnyrai.lt';
const INGEST_TOKEN   = process.env.INGEST_TOKEN   || 'ĮRAŠYK_SLAPTĄ_RAKTĄ';   // turi sutapti su .env OVERLAY_INGEST_TOKEN
const TOURNAMENT_ID  = process.env.TOURNAMENT_ID  || '10424';                  // Tournated turnyro ID
const POLL_INTERVAL_MS = Number(process.env.POLL_INTERVAL_MS || 20000);        // kas kiek siųsti (ms)

const GRAPHQL_URL = 'https://api.tournated.com/graphql';
const ORIGIN      = 'https://play.padel.lt';

// ── GraphQL pagalbinė ───────────────────────────────────────
async function gql(query) {
  const res = await fetch(GRAPHQL_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Origin': ORIGIN },
    body: JSON.stringify({ query }),
  });
  const json = await res.json();
  if (json.errors) throw new Error(JSON.stringify(json.errors));
  return json.data;
}

// ── Turnyro kategorijos ─────────────────────────────────────
async function fetchTournament(id) {
  const data = await gql(`{
    tournament(id: ${id}) {
      title
      tournamentCategory { id category { id name } mde }
    }
  }`);
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

// ── Vienas ciklas: surinkti viską ir nusiųsti ───────────────
async function pushOnce() {
  const tournament = await fetchTournament(TOURNAMENT_ID);
  if (!tournament) throw new Error(`Turnyras ${TOURNAMENT_ID} nerastas`);

  const categories = tournament.tournamentCategory || [];
  const groupsByCategory = {};

  for (const cat of categories) {
    try {
      groupsByCategory[String(cat.id)] = await fetchGroups(cat.id);
    } catch (e) {
      console.error(`  ! Kategorija ${cat.id}: ${e.message}`);
      groupsByCategory[String(cat.id)] = [];
    }
  }

  const snapshot = {
    tournament_id: TOURNAMENT_ID,
    title: tournament.title || null,
    categories,
    groups_by_category: groupsByCategory,
  };

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

  const catCount = categories.length;
  const groupCount = Object.values(groupsByCategory).reduce((n, g) => n + g.length, 0);
  console.log(`✅ [${new Date().toLocaleTimeString()}] Nusiųsta: "${tournament.title}" — ${catCount} kat., ${groupCount} grupių`);
}

// ── Pagrindinis ciklas ──────────────────────────────────────
async function loop() {
  console.log(`🏓 Overlay push paleistas`);
  console.log(`   Turnyras: ${TOURNAMENT_ID}`);
  console.log(`   Svetainė: ${SITE_URL}`);
  console.log(`   Intervalas: ${POLL_INTERVAL_MS / 1000}s\n`);

  if (INGEST_TOKEN === 'ĮRAŠYK_SLAPTĄ_RAKTĄ') {
    console.error('❌ Pirma įrašyk INGEST_TOKEN (tą patį, kaip serverio .env OVERLAY_INGEST_TOKEN).');
    process.exit(1);
  }

  for (;;) {
    try {
      await pushOnce();
    } catch (e) {
      console.error(`❌ Klaida: ${e.message}`);
    }
    await new Promise((r) => setTimeout(r, POLL_INTERVAL_MS));
  }
}

loop();
