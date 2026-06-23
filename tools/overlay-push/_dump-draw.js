// Vienkartinis: parodo, kaip Tournated struktūrizuoja vienos kategorijos draw'us
// (MAIN / 5-8 / 3 vietos ir pan.). Paleidimas:  node _dump-draw.js
const GRAPHQL_URL = 'https://api.tournated.com/graphql';
const ORIGIN = 'https://play.padel.lt';
const CATEGORY = 54712;

async function gql(query) {
  const res = await fetch(GRAPHQL_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Origin': ORIGIN },
    body: JSON.stringify({ query }),
  });
  return res.json();
}

const data = await gql(`{ draws(filter: { tournamentCategory: ${CATEGORY} }) }`);
const draws = data?.data?.draws;
if (!Array.isArray(draws)) { console.log(JSON.stringify(data, null, 2)); process.exit(0); }

console.log(`Draw'ų skaičius: ${draws.length}\n`);
for (const d of draws) {
  console.log('=== draw ===');
  console.log('  laukai:', Object.keys(d).join(', '));
  console.log(`  id=${d.id}  segment=${d.segment ?? '—'}  title="${d.title ?? '—'}"  type=${d.type ?? '—'}  size=${d.size ?? '—'}`);
  for (const r of (d.rounds || [])) {
    const seeds = r.seeds || [];
    const withTeams = seeds.filter((s) => (s.teams || []).length).length;
    console.log(`    round: "${r.title ?? ''}"  seeds=${seeds.length}  su komandomis=${withTeams}`);
  }
  console.log('');
}
