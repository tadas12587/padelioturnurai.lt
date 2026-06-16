@extends('overlays.base')

@section('styles')
    #stage { font-family: 'Barlow', system-ui, sans-serif; --cols: 1; }

    /* ── Header (logo + overlay title) ───────────────────────── */
    .ov-head { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; padding-left: 2px; }
    .ov-head img { height: 54px; width: auto; object-fit: contain; filter: drop-shadow(0 2px 7px rgba(0,0,0,.6)); }
    .ov-head .ov-title { font-family: 'Oswald', sans-serif; font-weight: 700; text-transform: uppercase;
        letter-spacing: .05em; font-size: 30px; line-height: 1.05; color: var(--ov-text);
        text-shadow: 0 2px 7px rgba(0,0,0,.5); }

    /* ── Responsive grid (column count set per render via --cols) ─ */
    .wrap { display: grid; gap: 14px; grid-template-columns: repeat(var(--cols), minmax(0, 1fr)); align-items: start; }

    /* ── Group card ──────────────────────────────────────────── */
    .card { position: relative; border-radius: 8px; overflow: hidden; background: var(--ov-bg);
        border: 1px solid rgba(127,127,127,.28); border-top: 3px solid var(--ov-accent);
        box-shadow: 0 20px 45px -20px rgba(0,0,0,.75), 0 2px 10px rgba(0,0,0,.35); }
    .card-head { display: flex; align-items: center; gap: 10px; padding: 11px 16px 8px; }
    .card-head::before { content: ''; width: 9px; height: 9px; flex: none; background: var(--ov-accent);
        box-shadow: 0 0 12px var(--ov-accent); transform: rotate(45deg); }
    .card-head .gname { font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .1em; font-size: 15px; color: var(--ov-accent); }

    /* ── Standings table ─────────────────────────────────────── */
    table { width: 100%; border-collapse: collapse; }
    thead th { font-family: 'Oswald', sans-serif; font-weight: 500; text-transform: uppercase;
        letter-spacing: .08em; font-size: 11px; color: var(--ov-muted); padding: 2px 14px 8px; text-align: right; }
    thead th.col-place, thead th.col-name { text-align: left; }
    tbody td { padding: 9px 14px; font-size: 17px; text-align: right; color: var(--ov-text); font-variant-numeric: tabular-nums; }
    tbody td.col-place { width: 48px; text-align: left; }
    tbody td.col-name { text-align: left; font-weight: 500; line-height: 1.2; padding-right: 10px; }
    tbody td.col-name .pl { display: block; }
    tbody tr + tr td { border-top: 1px solid rgba(127,127,127,.14); }
    tbody tr:nth-child(even) { background: rgba(127,127,127,.08); }
    tbody tr.leader { background: rgba(127,127,127,.12); box-shadow: inset 3px 0 0 var(--ov-accent); }
    tbody tr.leader td { border-top-color: transparent; }
    td.col-points { font-family: 'Oswald', sans-serif; font-weight: 600; font-size: 19px; color: var(--ov-accent); }

    /* ── Rank chip / medals ──────────────────────────────────── */
    .rank { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px;
        border-radius: 50%; font-family: 'Oswald', sans-serif; font-weight: 600; font-size: 13px;
        color: var(--ov-text); background: rgba(127,127,127,.32); }
    .rank.m1 { background: linear-gradient(145deg, #FCE38A, #E8B225); color: #3a2c00; }
    .rank.m2 { background: linear-gradient(145deg, #EEF1F5, #B9C0CA); color: #2a2f38; }
    .rank.m3 { background: linear-gradient(145deg, #E8A66A, #C2702F); color: #3a1e07; }

    /* ── Dense mode (5+ groups) ──────────────────────────────── */
    .wrap.dense td { font-size: 14px; padding: 6px 12px; }
    .wrap.dense td.col-points { font-size: 16px; }
    .wrap.dense .card-head .gname { font-size: 13px; }
    .wrap.dense thead th { font-size: 10px; }
    .wrap.dense .rank { width: 22px; height: 22px; font-size: 12px; }

    /* ── Lower third ─────────────────────────────────────────── */
    .lower { margin-top: 14px; display: flex; align-items: center; gap: 12px; padding: 12px 18px;
        border-radius: 6px; background: var(--ov-accent); color: #0A0A0F; box-shadow: 0 16px 34px -16px rgba(0,0,0,.65); }
    .lower .tag { font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .14em; font-size: 12px; opacity: .8; }
    .lower .tag::before { content: '▸ '; }
    .lower .txt { font-family: 'Barlow', sans-serif; font-weight: 600; font-size: 16px; }

    /* ── Bracket ─────────────────────────────────────────────── */
    .bracket { display: flex; gap: 40px; align-items: center; background: var(--ov-bg);
        border: 1px solid rgba(127,127,127,.28); border-top: 3px solid var(--ov-accent); border-radius: 8px;
        padding: 24px; box-shadow: 0 20px 45px -20px rgba(0,0,0,.75); }
    .round { display: flex; flex-direction: column; gap: 22px; }
    .match { border: 1px solid rgba(127,127,127,.22); border-radius: 6px; overflow: hidden; min-width: 230px;
        background: rgba(0,0,0,.18); }
    .team { padding: 9px 13px; font-size: 16px; display: flex; justify-content: space-between; gap: 10px; color: var(--ov-text); }
    .team + .team { border-top: 1px solid rgba(127,127,127,.18); }
    .team.win { color: var(--ov-accent); font-weight: 700; }
@endsection

@section('render_fn_body')
    // ── Top header: tournament logo + name (always shown) ───────
    const bigTitle = d.tournament_title || d.title || '';
    const headerHtml = (bigTitle || d.logo)
        ? `<div class="ov-head">${d.logo ? `<img src="${d.logo}" alt="">` : ''}${bigTitle ? `<span class="ov-title">${bigTitle}</span>` : ''}</div>`
        : '';

    // ── Bracket window ──────────────────────────────────────────
    if ((d.window_type || 'groups') === 'bracket') {
        let html = headerHtml;
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

    // ── Groups window ───────────────────────────────────────────
    const medals = { 1: 'm1', 2: 'm2', 3: 'm3' };
    const colLabels = { place: '#', name: 'Pora', points: 'Taškai', wins: 'W', losses: 'L', played: 'Ž' };
    const cs = d.columns || ['place', 'name', 'points', 'wins', 'losses'];
    const n = d.subgroup_count || (d.groups ? d.groups.length : 0);
    const cols = n <= 1 ? 1 : (n === 2 ? 2 : (n === 3 ? 3 : (n === 4 ? 2 : 3)));
    stage.style.setProperty('--cols', cols);

    let html = headerHtml;
    html += `<div class="wrap${n >= 5 ? ' dense' : ''}">`;
    for (const g of (d.groups || [])) {
        html += `<div class="card"><div class="card-head"><span class="gname">${g.name || ''}</span></div>`;
        html += `<table><thead><tr>`;
        for (const c of cs) html += `<th class="col-${c}">${colLabels[c] || c}</th>`;
        html += `</tr></thead><tbody>`;
        for (const r of g.rows) {
            html += `<tr${r.place === 1 ? ' class="leader"' : ''}>`;
            for (const c of cs) {
                if (c === 'place') {
                    const m = medals[r.place] ? ' ' + medals[r.place] : '';
                    html += `<td class="col-place"><span class="rank${m}">${r.place ?? '–'}</span></td>`;
                } else if (c === 'name') {
                    const players = String(r.name || '').split(' / ');
                    html += `<td class="col-name">${players.map(p => `<span class="pl">${p}</span>`).join('')}</td>`;
                } else {
                    const v = (r[c] === null || r[c] === undefined) ? '–' : r[c];
                    html += `<td class="col-${c}">${v}</td>`;
                }
            }
            html += `</tr>`;
        }
        html += `</tbody></table></div>`;
    }
    html += `</div>`;
    if (d.next_match) html += `<div class="lower"><span class="tag">Toliau</span><span class="txt">${d.next_match}</span></div>`;
    stage.innerHTML = html;
@endsection
