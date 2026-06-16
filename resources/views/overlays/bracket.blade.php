@extends('overlays.base')

@section('styles')
    .bracket { display: flex; gap: 40px; align-items: center;
        background: rgba(17,17,24,.92); border: 1px solid var(--accent); padding: 24px; }
    .round { display: flex; flex-direction: column; gap: 24px; }
    .match { background: #0A0A0F; border: 1px solid rgba(255,255,255,.1); min-width: 220px; }
    .team { padding: 8px 12px; font-size: 16px; display: flex; justify-content: space-between; }
    .team + .team { border-top: 1px solid rgba(255,255,255,.1); }
    .team.win { color: var(--accent); font-weight: 700; }
    .title { color: var(--accent); font-weight: 800; text-transform: uppercase;
        letter-spacing: .15em; font-size: 14px; margin-bottom: 12px; }
@endsection

@section('render_fn_body')
    document.documentElement.style.setProperty('--accent', d.accent || '#C9A84C');
    let html = '';
    if (d.title) html += `<div class="title">${d.title}</div>`;
    html += `<div class="bracket">`;
    for (const round of (d.rounds || [])) {
        html += `<div class="round">`;
        for (const m of (round.matches || [])) {
            html += `<div class="match">`;
            for (const t of (m.teams || [])) {
                html += `<div class="team ${t.winner ? 'win' : ''}">
                    <span>${t.name || 'TBD'}</span><span>${t.score ?? ''}</span></div>`;
            }
            html += `</div>`;
        }
        html += `</div>`;
    }
    html += `</div>`;
    root.innerHTML = html;
@endsection
