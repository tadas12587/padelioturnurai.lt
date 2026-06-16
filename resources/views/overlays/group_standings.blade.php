@extends('overlays.base')

@section('styles')
    .card { background: rgba(17,17,24,.92); border: 1px solid var(--accent);
        backdrop-filter: blur(4px); }
    .wrap { display: grid; gap: 16px; }
    .wrap.cols-1 { grid-template-columns: 1fr; }
    .wrap.cols-2 { grid-template-columns: 1fr 1fr; }
    .wrap.cols-4 { grid-template-columns: repeat(2, 1fr); }
    .title { color: var(--accent); font-weight: 800; letter-spacing:.15em;
        text-transform: uppercase; font-size: 14px; padding: 12px 16px 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 8px 16px; text-align: left; font-size: 18px; }
    thead th { color: var(--accent); font-size: 12px; text-transform: uppercase; }
    tbody tr + tr { border-top: 1px solid rgba(255,255,255,.08); }
    .lower { margin-top: 16px; background: var(--accent); color: #0A0A0F;
        font-weight: 700; padding: 10px 18px; }
@endsection

@section('render_fn_body')
    document.documentElement.style.setProperty('--accent', d.accent || '#C9A84C');
    const n = d.subgroup_count || (d.groups ? d.groups.length : 0);
    const cols = n >= 4 ? 'cols-4' : (n === 2 ? 'cols-2' : 'cols-1');
    const colLabels = { place: '#', name: 'Pora', wins: 'W', losses: 'L', played: 'Ž' };
    const cs = d.columns || ['place','name','wins','losses'];

    let html = '';
    if (d.title) html += `<div class="title">${d.title}</div>`;
    html += `<div class="wrap ${cols}">`;
    for (const g of (d.groups || [])) {
        html += `<div class="card"><div class="title">${g.name || ''}</div><table><thead><tr>`;
        for (const c of cs) html += `<th>${colLabels[c] || c}</th>`;
        html += `</tr></thead><tbody>`;
        for (const r of g.rows) {
            html += '<tr>';
            for (const c of cs) html += `<td>${r[c] ?? '-'}</td>`;
            html += '</tr>';
        }
        html += `</tbody></table></div>`;
    }
    html += `</div>`;
    if (d.next_match) html += `<div class="lower">${d.next_match}</div>`;
    stage.innerHTML = html;
@endsection
