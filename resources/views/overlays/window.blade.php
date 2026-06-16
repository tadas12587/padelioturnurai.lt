@extends('overlays.base')

@section('styles')
    .panel-card { background: var(--ov-bg); border: 1px solid var(--ov-accent); }
    .ov-header { display: flex; align-items: center; gap: 12px; padding: 12px 16px 0; }
    .ov-header img { height: 28px; width: auto; object-fit: contain; }
    .ov-title { color: var(--ov-accent); font-weight: 800; letter-spacing: .15em;
        text-transform: uppercase; font-size: 14px; }
    .wrap { display: grid; gap: 16px; }
    .wrap.cols-1 { grid-template-columns: 1fr; }
    .wrap.cols-2 { grid-template-columns: 1fr 1fr; }
    .wrap.cols-4 { grid-template-columns: repeat(2, 1fr); }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 8px 16px; text-align: left; font-size: 18px; }
    thead th { color: var(--ov-accent); font-size: 12px; text-transform: uppercase; }
    tbody tr + tr { border-top: 1px solid rgba(127,127,127,.25); }
    .lower { margin-top: 16px; background: var(--ov-accent); color: var(--ov-bg);
        font-weight: 700; padding: 10px 18px; }
    .bracket { display: flex; gap: 40px; align-items: center; background: var(--ov-bg);
        border: 1px solid var(--ov-accent); padding: 24px; }
    .round { display: flex; flex-direction: column; gap: 24px; }
    .match { background: rgba(0,0,0,.25); border: 1px solid rgba(127,127,127,.25); min-width: 220px; }
    .team { padding: 8px 12px; font-size: 16px; display: flex; justify-content: space-between; }
    .team + .team { border-top: 1px solid rgba(127,127,127,.25); }
    .team.win { color: var(--ov-accent); font-weight: 700; }
@endsection

@section('render_fn_body')
    if ((d.window_type || 'groups') === 'bracket') {
        let html = '';
        if (d.title) html += `<div class="ov-header"><span class="ov-title">${d.title}</span></div>`;
        html += `<div class="bracket">`;
        for (const round of (d.rounds || [])) {
            html += `<div class="round">`;
            for (const m of (round.matches || [])) {
                html += `<div class="match">`;
                for (const t of (m.teams || [])) {
                    html += `<div class="team ${t.winner ? 'win' : ''}"><span>${t.name || 'TBD'}</span><span>${t.score ?? ''}</span></div>`;
                }
                html += `</div>`;
            }
            html += `</div>`;
        }
        html += `</div>`;
        stage.innerHTML = html;
        return;
    }

    const medals = { 1: '🥇', 2: '🥈', 3: '🥉' };
    const colLabels = { place: '#', name: 'Pora', points: 'Taškai', wins: 'W', losses: 'L', played: 'Ž' };
    const cs = d.columns || ['place', 'name', 'points', 'wins', 'losses'];
    const n = d.subgroup_count || (d.groups ? d.groups.length : 0);
    const cols = n >= 4 ? 'cols-4' : (n === 2 ? 'cols-2' : 'cols-1');

    let html = '';
    if (d.title || d.logo) {
        html += `<div class="ov-header">`;
        if (d.logo) html += `<img src="${d.logo}" alt="">`;
        if (d.title) html += `<span class="ov-title">${d.title}</span>`;
        html += `</div>`;
    }
    html += `<div class="wrap ${cols}">`;
    for (const g of (d.groups || [])) {
        html += `<div class="panel-card"><div class="ov-header"><span class="ov-title">${g.name || ''}</span></div><table><thead><tr>`;
        for (const c of cs) html += `<th>${colLabels[c] || c}</th>`;
        html += `</tr></thead><tbody>`;
        for (const r of g.rows) {
            html += '<tr>';
            for (const c of cs) {
                let v = r[c] ?? '-';
                if (c === 'place' && r.place && medals[r.place]) v = `${medals[r.place]} ${r.place}`;
                html += `<td>${v}</td>`;
            }
            html += '</tr>';
        }
        html += `</tbody></table></div>`;
    }
    html += `</div>`;
    if (d.next_match) html += `<div class="lower">${d.next_match}</div>`;
    stage.innerHTML = html;
@endsection
