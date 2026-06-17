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

// ── Vienos kategorijos dalyviai (poros) ─────────────────────
async function fetchParticipants(categoryId) {
  const data = await gql(`{
    entries(filter: { tournamentCategory: ${categoryId} }) {
      id seed registrationRequest { users { user { name surname } } }
    }
  }`);
  const pairName = (e) => (e.registrationRequest?.users || [])
    .map((u) => `${u.user?.name || ''} ${u.user?.surname || ''}`.trim()).filter(Boolean).join(' / ');
  return (data.entries || []).map((e) => ({
    id: e.id,
    name: pairName(e) || `#${e.id}`,
    seed: e.seed ?? null,
    pot: null,
  }));
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
  };
}

async function fetchMatches(tournamentId) {
  const data = await gql(`{
    matches(filter: { tournament: ${tournamentId} }) {
      id time date duration status isMatchInProgress firstScoreSubmittedAt round bracketType score
      court { id name }
      tournamentCategory { id category { name } }
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
  const third = thirdRound ? matchOf(thirdRound.seeds[0]) : null;

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
  const participantsByCategory = {};

  for (const cat of categories) {
    try {
      groupsByCategory[String(cat.id)] = await fetchGroups(cat.id);
    } catch (e) {
      console.error(`  ! Kategorija ${cat.id}: ${e.message}`);
      groupsByCategory[String(cat.id)] = [];
    }
    try {
      participantsByCategory[String(cat.id)] = await fetchParticipants(cat.id);
    } catch (e) {
      console.error(`  ! Dalyviai ${cat.id}: ${e.message}`);
      participantsByCategory[String(cat.id)] = [];
    }
  }

  const categoryStages = {};
  const bracketsByCategory = {};
  for (const cat of categories) {
    const groups = groupsByCategory[String(cat.id)] || [];
    let draws = [];
    try { draws = await fetchDraws(cat.id); } catch (_) { draws = []; }

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

    categoryStages[String(cat.id)] = {
      has_groups: groups.length > 0,
      has_bracket: segments.length > 0,
      draw_type: draws[0]?.type ?? null,
      draw_size: draws[0]?.size ?? null,
    };
    if (segments.length) bracketsByCategory[String(cat.id)] = { segments };
  }

  let matches = [];
  try { matches = await fetchMatches(tournamentId); } catch (e) { console.error(`  ! Matches: ${e.message}`); }

  const snapshot = {
    tournament_id: tournamentId,
    title: tournament.title || null,
    categories,
    groups_by_category: groupsByCategory,
    participants_by_category: participantsByCategory,
    category_stages: categoryStages,
    brackets_by_category: bracketsByCategory,
    matches,
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
  console.log(`✅ [${new Date().toLocaleTimeString()}] Nusiųsta: "${tournament.title}" — ${catCount} kat., ${groupCount} grupių, ${matches.length} susitikimų`);
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
