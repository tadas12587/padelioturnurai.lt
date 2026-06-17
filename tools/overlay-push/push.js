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

// ── Vienos kategorijos žaidynės (draws) ────────────────────
async function fetchDraws(categoryId) {
  // `draws` returns raw JSON objects; presence indicates an elimination/main draw.
  const data = await gql(`{ draws(filter: { tournamentCategory: ${categoryId} }) }`);
  return data.draws || [];
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
  const third = thirdRound ? matchOf(thirdRound.seeds[0]) : null;

  // Placement brackets: leftover rounds (not main, not 3rd place) grouped into
  // blocks, each ending in a "Nth place" round.
  const mainSet = new Set(main);
  const placements = [];
  let acc = [];
  for (const r of rounds) {
    if (mainSet.has(r) || r === thirdRound) continue;
    acc.push({ title: '', matches: (r.seeds || []).map(matchOf) });
    const m = (r.title || '').match(/(\d+)\D*place/i);
    if (m) {
      placements.push({ title: `Dėl ${m[1]} vietos`, rounds: acc });
      acc = [];
    }
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

// ── Vienas ciklas: surinkti viską ir nusiųsti ───────────────
async function pushOnce(tournamentId) {
  const tournament = await fetchTournament(tournamentId);
  if (!tournament) throw new Error(`Turnyras ${tournamentId} nerastas`);

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

  const categoryStages = {};
  const bracketsByCategory = {};
  for (const cat of categories) {
    const groups = groupsByCategory[String(cat.id)] || [];
    let draws = [];
    try { draws = await fetchDraws(cat.id); } catch (_) { draws = []; }
    categoryStages[String(cat.id)] = {
      has_groups: groups.length > 0,
      has_bracket: draws.length > 0,
      draw_type: draws[0]?.type ?? null,
      draw_size: draws[0]?.size ?? null,
    };
    if (draws[0]) {
      const b = extractBracket(draws[0]);
      if (b && b.rounds.length) bracketsByCategory[String(cat.id)] = b;
    }
  }

  const snapshot = {
    tournament_id: tournamentId,
    title: tournament.title || null,
    categories,
    groups_by_category: groupsByCategory,
    category_stages: categoryStages,
    brackets_by_category: bracketsByCategory,
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
  console.log(`   Turnyrai: iš admin (auto)${TOURNAMENT_ID ? ` arba ${TOURNAMENT_ID}` : ''}`);
  console.log(`   Svetainė: ${SITE_URL}`);
  console.log(`   Intervalas: ${POLL_INTERVAL_MS / 1000}s\n`);

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
