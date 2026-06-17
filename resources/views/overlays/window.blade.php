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
    .card-head .gseg { margin-left: auto; font-family: 'Oswald', sans-serif; font-weight: 600;
        text-transform: uppercase; letter-spacing: .08em; font-size: 11px; color: var(--ov-muted);
        padding: 2px 8px; border: 1px solid rgba(127,127,127,.35); border-radius: 999px; }
    .wrap.dense .card-head .gseg { font-size: 10px; padding: 1px 6px; }

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

    /* ── Bracket (tournament tree) ───────────────────────────── */
    .bracket { display: flex; align-items: stretch; padding: 4px 0; }
    .round { position: relative; display: flex; flex-direction: column; padding: 30px 30px 4px; }
    .round-title { position: absolute; top: 0; left: 0; right: 0; text-align: center;
        font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .12em; font-size: 12px; color: var(--ov-muted); }
    .round-matches { display: flex; flex-direction: column; justify-content: space-around; flex: 1; gap: 30px; }
    .round.is-last { justify-content: center; }
    .round.is-last .round-matches { flex: 0 0 auto; }
    .match-slot { position: relative; flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; }
    /* "Dėl N vietos" caption above a placement final */
    .match-place { align-self: stretch; margin-bottom: 5px; padding: 3px 11px; border-radius: 5px;
        background: rgba(127,127,127,.14); font-family: 'Oswald', sans-serif; font-weight: 600;
        text-transform: uppercase; letter-spacing: .08em; font-size: 11px; color: var(--ov-muted); }
    .match { position: relative; width: 232px; background: var(--ov-bg);
        border: 1px solid rgba(127,127,127,.28); border-left: 3px solid var(--ov-accent);
        border-radius: 6px; box-shadow: 0 12px 30px -18px rgba(0,0,0,.7); }
    .team { display: flex; justify-content: space-between; gap: 10px; padding: 8px 13px;
        font-size: 15px; color: var(--ov-text); }
    .team + .team { border-top: 1px solid rgba(127,127,127,.16); }
    .team .nm { font-family: 'Barlow', sans-serif; font-weight: 500; }
    .team .sets { display: flex; gap: 6px; }
    .team .g { font-family: 'Oswald', sans-serif; font-variant-numeric: tabular-nums;
        min-width: 14px; text-align: center; color: var(--ov-muted); }
    .team.win { background: rgba(127,127,127,.12); }
    .team.win .nm { font-weight: 700; color: var(--ov-accent); }
    .team.win .g { color: var(--ov-accent); }
    /* connectors: out from each slot → vertical join per pair → in to next match */
    .round:not(.is-last) .match-slot::after { content: ''; position: absolute; right: -30px; top: 50%;
        width: 30px; height: 2px; background: rgba(127,127,127,.5); }
    .round:not(.is-last) .match-slot:nth-child(odd)::before { content: ''; position: absolute;
        right: -30px; top: 50%; width: 2px; height: 50%; background: rgba(127,127,127,.5); }
    .round:not(.is-last) .match-slot:nth-child(even)::before { content: ''; position: absolute;
        right: -30px; bottom: 50%; width: 2px; height: 50%; background: rgba(127,127,127,.5); }
    .round:not(:first-child) .match::before { content: ''; position: absolute; left: -30px; top: 50%;
        width: 30px; height: 2px; background: rgba(127,127,127,.5); }
    /* full-screen + auto-fit */
    .bracket-screen { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; }
    .bracket-fit { transform-origin: center center; display: flex; flex-direction: column; align-items: center; gap: 18px; }
    /* multiple bracket segments (separate draws) side by side */
    .segments-row { display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-start; gap: 48px; }
    .segment { display: flex; flex-direction: column; align-items: center; gap: 12px; }
    .segment-title { font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .1em; font-size: 15px; color: var(--ov-accent); }
    .segment-body { display: flex; flex-direction: column; align-items: center; gap: 18px; }
    /* court / time caption */
    .match .mt { padding: 2px 13px 7px; font-family: 'Oswald', sans-serif; font-size: 11px;
        letter-spacing: .05em; color: var(--ov-muted); }
    /* placements (3rd place + consolation), visually secondary */
    .placements-row { display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-start; gap: 26px; }
    .placement { display: flex; flex-direction: column; align-items: center; }
    .placement-title { font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .1em; font-size: 12px; color: var(--ov-accent); opacity: .85; margin-bottom: 6px; }
    .placement .bracket { padding: 0; }
    .placement .match { width: 198px; }
    .placement .team { font-size: 13px; padding: 6px 11px; }
    /* 3rd place tucked under the final column */
    .third-under { margin-top: 10px; display: flex; flex-direction: column; align-items: center; }
    .third-under .match { width: 200px; }
    .third-under .team { font-size: 13px; padding: 6px 11px; }

    /* ── Schedule (order of play) ─────────────────────────────── */
    .sc-wrap { display: flex; flex-direction: column; gap: 14px; }
    .sc-empty { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: .1em;
        color: var(--ov-muted); padding: 18px; }
    .sc-cols { display: flex; flex-wrap: wrap; gap: 18px; align-items: flex-start; }
    .sc-col { flex: 1 1 200px; min-width: 200px; background: var(--ov-bg);
        border: 1px solid rgba(127,127,127,.22); border-radius: 8px; overflow: hidden; }
    .sc-col-head { font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase;
        letter-spacing: .08em; font-size: 14px; color: var(--ov-accent);
        padding: 9px 13px; border-bottom: 1px solid rgba(127,127,127,.22); }
    .sc-row, .sc-card { padding: 9px 13px; border-bottom: 1px solid rgba(127,127,127,.12); }
    .sc-list { display: flex; flex-direction: column; gap: 10px; }
    /* results — full-width bottom broadcast strip */
    .sc-ticker { position: fixed; left: 0; right: 0; bottom: 0; display: flex; align-items: stretch;
        background: var(--ov-bg); border-top: 3px solid var(--ov-accent);
        box-shadow: 0 -12px 34px -14px rgba(0,0,0,.65); }
    .sc-ticker-label { display: flex; align-items: center; padding: 0 28px; white-space: nowrap;
        font-family: 'Oswald', sans-serif; font-weight: 700; text-transform: uppercase;
        letter-spacing: .14em; font-size: 26px; color: var(--ov-accent);
        border-right: 1px solid rgba(127,127,127,.25); }
    .sc-ticker-items { display: flex; flex: 1; min-width: 0; overflow: hidden; }
    .res { flex: 1 1 0; min-width: 0; padding: 12px 22px; display: flex; flex-direction: column;
        justify-content: center; gap: 2px; border-right: 1px solid rgba(127,127,127,.16); }
    .res-cat { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: .08em;
        font-size: 11px; color: var(--ov-muted); margin-bottom: 3px; }
    .res-row { display: flex; align-items: center; gap: 8px; font-family: 'Barlow', sans-serif;
        font-size: 15px; color: var(--ov-text); min-width: 0; }
    .res-row::before { content: ''; width: 7px; height: 7px; flex: none; border-radius: 50%; }
    .res-row.w { color: var(--ov-accent); font-weight: 700; }
    .res-row.w::before { background: var(--ov-accent); box-shadow: 0 0 8px var(--ov-accent); }
    .res-team { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .res-score { margin-top: 6px; padding-left: 15px; font-family: 'Oswald', sans-serif;
        font-variant-numeric: tabular-nums; font-weight: 600; font-size: 20px; letter-spacing: .04em;
        color: var(--ov-accent); }
    .sc-card { border: 1px solid rgba(127,127,127,.22); border-left: 3px solid rgba(127,127,127,.4);
        border-radius: 6px; background: var(--ov-bg); }
    .sc-card.live { border-left-color: var(--ov-accent); box-shadow: 0 0 16px -6px var(--ov-accent); }
    .sc-meta { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: .06em;
        font-size: 11px; color: var(--ov-muted); margin-bottom: 4px; }
    .sc-teams { display: flex; flex-direction: column; gap: 2px; font-family: 'Barlow', sans-serif;
        font-size: 14px; color: var(--ov-text); }
    .sc-teams .win { color: var(--ov-accent); font-weight: 700; }
    .sc-score { font-family: 'Oswald', sans-serif; font-variant-numeric: tabular-nums;
        font-size: 12px; color: var(--ov-muted); margin-top: 4px; }

    /* ── Sponsors ────────────────────────────────────────────── */
    .sp-item { opacity: 0; transition: opacity .6s ease, transform .6s cubic-bezier(.16,1,.3,1); }
    .sp-item.show { opacity: 1; }
    .spons.corner { position: relative; width: 240px; height: 120px; background: var(--ov-bg);
        border: 1px solid rgba(127,127,127,.28); border-top: 3px solid var(--ov-accent); border-radius: 8px;
        box-shadow: 0 20px 45px -20px rgba(0,0,0,.75); }
    .spons.corner .sp-item { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 18px; transform: scale(.96); }
    .spons.corner .sp-item.show { transform: none; }
    .spons.corner img { width: 200px; height: 84px; object-fit: contain; }
    .spons.bar { position: fixed; left: 0; right: 0; bottom: 0; height: 92px; background: var(--ov-bg);
        border-top: 3px solid var(--ov-accent); box-shadow: 0 -10px 30px -12px rgba(0,0,0,.6); }
    .spons.bar .sp-item { position: absolute; inset: 0; display: flex; align-items: center; gap: 22px; padding: 0 48px; transform: translateY(12px); }
    .spons.bar .sp-item.show { transform: none; }
    .spons.bar img { width: 160px; height: 64px; object-fit: contain; flex: none; }
    .spons.bar .meta { display: flex; flex-direction: column; }
    .spons.bar .nm { font-family: 'Oswald',sans-serif; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; font-size: 22px; color: var(--ov-text); }
    .spons.bar .url { font-family: 'Barlow',sans-serif; font-size: 16px; color: var(--ov-accent); }
    .spons.full { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center;
        background: radial-gradient(120% 120% at 50% 30%, var(--ov-bg), #000); }
    .spons.full .sp-item { position: absolute; display: flex; flex-direction: column; align-items: center; gap: 28px; transform: scale(.92); }
    .spons.full .sp-item.show { transform: none; }
    .spons.full img { width: min(640px, 60vw); height: min(360px, 52vh); object-fit: contain; filter: drop-shadow(0 12px 40px rgba(0,0,0,.5)); }
    .spons.full .nm { font-family: 'Oswald',sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; font-size: 34px; color: var(--ov-text); }

    /* ── Draw (burtai) ───────────────────────────────────────── */
    .draw-stage { position: fixed; inset: 0; padding: 28px 34px; display: flex; flex-direction: column; }
    .draw-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
    .draw-head .left { display: flex; align-items: center; gap: 12px; }
    .draw-head img { height: 46px; width: auto; object-fit: contain; }
    .draw-head .tt { font-family: 'Oswald',sans-serif; font-weight: 700; font-size: 22px; color: var(--ov-text); line-height: 1.1; }
    .draw-head .cat { font-size: 13px; color: var(--ov-muted); }
    .draw-head .badge { font-family: 'Oswald',sans-serif; font-weight: 700; letter-spacing: .12em; font-size: 26px; color: var(--ov-accent); }
    .draw-head .pot { font-family: 'Oswald',sans-serif; font-size: 12px; font-weight: 600; color: #0A0A0F; background: var(--ov-accent); padding: 3px 10px; border-radius: 6px; margin-left: 12px; }
    .draw-body { flex: 1; display: flex; gap: 18px; min-height: 0; }
    .draw-grid { flex: 1; display: grid; gap: 12px; align-content: start; }
    .dg-card { background: var(--ov-bg); border: 1px solid rgba(127,127,127,.28); border-top: 3px solid var(--ov-accent); border-radius: 8px; padding: 8px 12px; }
    .dg-card .gname { font-family: 'Oswald',sans-serif; font-weight: 600; letter-spacing: .1em; font-size: 14px; color: var(--ov-accent); margin-bottom: 6px; }
    .dg-slot { display: flex; gap: 8px; font-size: 15px; padding: 5px 0; border-top: 1px solid rgba(127,127,127,.14); }
    .dg-slot:first-of-type { border-top: 0; }
    .dg-slot .pos { color: var(--ov-muted); width: 18px; }
    .dg-slot.empty .nm { color: #5a5a66; font-style: italic; }
    .dg-slot.bye .nm { color: #C9A84C; font-style: italic; opacity: .85; }
    .dg-slot.just-in { animation: drawIn .55s cubic-bezier(.16,1,.3,1) both; }
    @keyframes drawIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: none; } }
    /* bracket draw: first-round seeding sheet (match cards in a grid) */
    .draw-bracket { flex: 1; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 14px 30px; align-content: start; }
    .dmatch { display: flex; align-items: center; gap: 12px; }
    .dmatch-no { font-family: 'Oswald',sans-serif; font-weight: 600; font-size: 14px; color: var(--ov-muted);
        min-width: 22px; text-align: right; flex: none; }
    .dmatch-card { flex: 1; background: var(--ov-bg); border: 1px solid rgba(127,127,127,.28);
        border-left: 3px solid var(--ov-accent); border-radius: 8px; overflow: hidden;
        box-shadow: 0 14px 32px -20px rgba(0,0,0,.7); }
    .dteam { display: flex; align-items: center; gap: 12px; padding: 10px 14px; font-size: 16px; color: var(--ov-text); }
    .dteam + .dteam { border-top: 1px solid rgba(127,127,127,.16); }
    .dteam .pos { font-family: 'Oswald',sans-serif; font-size: 12px; color: var(--ov-muted); min-width: 18px; text-align: center; }
    .dteam .nm { font-weight: 500; }
    .dteam.empty .nm { color: #5a5a66; font-style: italic; }
    .dteam.bye .nm { color: #C9A84C; font-style: italic; opacity: .85; }
    .dteam.just-in { animation: drawIn .55s cubic-bezier(.16,1,.3,1) both; }
    .draw-pool { width: 240px; flex: none; }
    .draw-pool .lbl { font-family: 'Oswald',sans-serif; text-transform: uppercase; letter-spacing: .08em; font-size: 11px; color: var(--ov-muted); margin-bottom: 8px; }
    .draw-pool .chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .draw-pool .chip { font-size: 12px; background: rgba(127,127,127,.16); padding: 4px 9px; border-radius: 12px; color: var(--ov-text); }
    .draw-reveal { position: fixed; left: 50%; top: 56%; transform: translate(-50%,-50%); background: var(--ov-accent); color: #0A0A0F; padding: 12px 26px; border-radius: 10px; text-align: center; box-shadow: 0 20px 50px -18px rgba(0,0,0,.7); }
    .draw-reveal .k { font-family: 'Oswald',sans-serif; font-weight: 600; letter-spacing: .14em; font-size: 11px; opacity: .7; }
    .draw-reveal .nm { font-family: 'Barlow',sans-serif; font-weight: 700; font-size: 22px; margin-top: 3px; }
    .draw-reveal .to { font-size: 12px; margin-top: 2px; }
    .draw-spons { margin-top: 14px; overflow: hidden; }
    .sp-track { display: flex; width: max-content; animation-name: spMarquee; animation-timing-function: linear; animation-iteration-count: infinite; }
    @keyframes spMarquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
    .sp-tile { width: 150px; height: 56px; flex: none; margin-right: 12px; display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,.06); border: 1px solid rgba(127,127,127,.22); border-radius: 8px; padding: 8px; }
    .sp-tile img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .draw-done { margin-left: 12px; color: var(--ov-accent); font-family: 'Oswald',sans-serif; font-weight: 700; letter-spacing: .14em; }
    /* Reserve a wide band on the camera side for the live video feed. */
    .draw-corner-bottom-right .draw-head, .draw-corner-top-right .draw-head,
    .draw-corner-bottom-right .draw-body, .draw-corner-top-right .draw-body,
    .draw-corner-bottom-right .draw-spons, .draw-corner-top-right .draw-spons { padding-right: 30%; }
    .draw-corner-bottom-left .draw-head, .draw-corner-top-left .draw-head,
    .draw-corner-bottom-left .draw-body, .draw-corner-top-left .draw-body,
    .draw-corner-bottom-left .draw-spons, .draw-corner-top-left .draw-spons { padding-left: 30%; }

@endsection

@section('render_fn_body')
    // The results ticker lives on <body> (outside the positioned/transformed
    // containers) so it can pin to the real screen bottom. Clear it each render;
    // the results branch re-creates it.
    { const _t = document.getElementById('ov-ticker'); if (_t) _t.remove(); }
    if ((d.window_type || 'groups') !== 'draw') {
        const _r = document.getElementById('draw-reveal-host'); if (_r) _r.remove();
        const _d = document.getElementById('ov-draw'); if (_d) _d.remove();
        clearInterval(window.__drawSpons);
        window.__drawLastSlot = undefined;
    }

    if ((d.window_type || 'groups') === 'sponsors') {
        clearInterval(window.__spTimer);
        const items = d.items || [];
        if (!items.length) { stage.innerHTML = ''; return; }
        const variant = d.variant || 'corner';

        const itemHtml = (it, i) => {
            if (variant === 'bar') {
                const meta = (it.name || it.url)
                    ? `<div class="meta">${it.name ? `<span class="nm">${it.name}</span>` : ''}${it.url ? `<span class="url">${it.url}</span>` : ''}</div>`
                    : '';
                return `<div class="sp-item${i === 0 ? ' show' : ''}"><img src="${it.logo}" alt="">${meta}</div>`;
            }
            // fullscreen and corner: logo only (name/url shown only in the bar)
            return `<div class="sp-item${i === 0 ? ' show' : ''}"><img src="${it.logo}" alt=""></div>`;
        };

        const cls = variant === 'bar' ? 'bar' : (variant === 'fullscreen' ? 'full' : 'corner');
        stage.innerHTML = `<div class="spons ${cls}">${items.map(itemHtml).join('')}</div>`;

        const els = stage.querySelectorAll('.sp-item');
        if (els.length > 1) {
            let i = 0;
            window.__spTimer = setInterval(() => {
                els[i].classList.remove('show');
                i = (i + 1) % els.length;
                els[i].classList.add('show');
            }, (d.rotate_seconds || 6) * 1000);
        }
        return;
    }

    // ── Top header: tournament logo + name (always shown) ───────
    const bigTitle = d.tournament_title || d.title || '';
    const headerHtml = (bigTitle || d.logo)
        ? `<div class="ov-head">${d.logo ? `<img src="${d.logo}" alt="">` : ''}${bigTitle ? `<span class="ov-title">${bigTitle}</span>` : ''}</div>`
        : '';

    // ── Bracket window (tournament tree) ────────────────────────
    if ((d.window_type || 'groups') === 'bracket') {
        const b = d.bracket || { segments: [] };
        // Accept the legacy single-bracket shape as one segment.
        const segments = b.segments
            || (b.rounds ? [{ label: '', is_main: true, rounds: b.rounds, third: b.third, placements: b.placements }] : []);

        const setCells = (sets) => (sets || '').trim().split(/\s+/).filter(Boolean)
            .map((g) => `<span class="g">${g}</span>`).join('');
        const team = (name, sets, win) =>
            `<div class="team ${win ? 'win' : ''}"><span class="nm">${name || 'TBD'}</span><span class="sets">${setCells(sets)}</span></div>`;
        const courtLine = (m) => (m.court || m.time)
            ? `<div class="mt">${[m.court, m.time].filter(Boolean).join(' · ')}</div>` : '';
        const matchBox = (m) =>
            `${m.place ? `<div class="match-place">${m.place}</div>` : ''}<div class="match">${team(m.team1, m.sets1, m.winner === 1)}${team(m.team2, m.sets2, m.winner === 2)}${courtLine(m)}</div>`;

        const treeHtml = (rounds) => {
            let h = '<div class="bracket">';
            rounds.forEach((round, ri) => {
                const last = ri === rounds.length - 1;
                h += `<div class="round${last ? ' is-last' : ''}">${round.title ? `<div class="round-title">${round.title}</div>` : ''}<div class="round-matches">`;
                for (const m of round.matches) h += `<div class="match-slot">${matchBox(m)}</div>`;
                h += '</div></div>';
            });
            return h + '</div>';
        };

        // One segment = main tree (3rd place tucked under the final) + placement row.
        const segmentTree = (seg) => {
            const rounds = seg.rounds || [];
            let mainHtml = '<div class="bracket">';
            rounds.forEach((round, ri) => {
                const last = ri === rounds.length - 1;
                mainHtml += `<div class="round${last ? ' is-last' : ''}">`;
                mainHtml += round.title ? `<div class="round-title">${round.title}</div>` : '';
                mainHtml += '<div class="round-matches">';
                for (const m of round.matches) mainHtml += `<div class="match-slot">${matchBox(m)}</div>`;
                mainHtml += '</div>';
                if (last && seg.third) {
                    mainHtml += `<div class="third-under"><div class="placement-title">Dėl 3 vietos</div>${matchBox(seg.third)}</div>`;
                }
                mainHtml += '</div>';
            });
            mainHtml += '</div>';

            let h = mainHtml;
            const blocks = (seg.placements || []);
            if (blocks.length) {
                h += '<div class="placements-row">';
                for (const blk of blocks) {
                    h += `<div class="placement"><div class="placement-title">${blk.title}</div>${treeHtml(blk.rounds)}</div>`;
                }
                h += '</div>';
            }
            return h;
        };

        // Show a per-segment heading except for a single main tree shown alone (stays clean).
        const showTitles = segments.length > 1 || (segments.length === 1 && !segments[0].is_main);

        let inner = headerHtml + '<div class="segments-row">';
        for (const seg of segments) {
            inner += '<div class="segment">';
            if (showTitles && seg.label) inner += `<div class="segment-title">${seg.label}</div>`;
            inner += `<div class="segment-body">${segmentTree(seg)}</div></div>`;
        }
        inner += '</div>';

        stage.innerHTML = `<div class="bracket-screen"><div class="bracket-fit">${inner}</div></div>`;

        const fit = stage.querySelector('.bracket-fit');
        if (fit) {
            fit.style.transform = 'none';
            requestAnimationFrame(() => {
                const w = fit.scrollWidth, h = fit.scrollHeight;
                if (w && h) {
                    const s = Math.min(1, (window.innerWidth - 80) / w, (window.innerHeight - 80) / h);
                    fit.style.transform = `scale(${s})`;
                }
            });
        }
        return;
    }

    // ── Schedule (order of play) ────────────────────────────────
    if ((d.window_type || 'groups') === 'schedule') {
        const sc = d.schedule || {};
        const variant = d.schedule_variant || 'by_court';
        const pair = (t) => (t && t.length) ? t.join(' / ') : 'TBD';
        const teams = (m) =>
            `<div class="sc-teams"><span class="${m.winner === 1 ? 'win' : ''}">${pair(m.team1)}</span>`
          + `<span class="${m.winner === 2 ? 'win' : ''}">${pair(m.team2)}</span></div>`;
        const meta = (parts) => `<div class="sc-meta">${parts.filter(Boolean).join(' · ')}</div>`;

        // Results — a full-width bottom broadcast strip; no header, winner + score emphasised.
        if (variant === 'results') {
            const items = sc.items || [];
            const resRow = (names, won) => `<div class="res-row${won ? ' w' : ''}"><span class="res-team">${names}</span></div>`;
            let bar = '<div class="sc-ticker"><div class="sc-ticker-label">Rezultatai</div><div class="sc-ticker-items">';
            if (!items.length) {
                bar += '<div class="sc-empty">Nėra rezultatų</div>';
            } else {
                for (const m of items) {
                    bar += '<div class="res">'
                        + (m.category ? `<div class="res-cat">${[m.category, m.round].filter(Boolean).join(' · ')}</div>` : '')
                        + resRow(pair(m.team1), m.winner === 1)
                        + resRow(pair(m.team2), m.winner === 2)
                        + (m.score ? `<div class="res-score">${m.score}</div>` : '')
                        + '</div>';
                }
            }
            bar += '</div></div>';
            stage.innerHTML = '';
            const host = document.createElement('div');
            host.id = 'ov-ticker';
            host.innerHTML = bar;
            document.body.appendChild(host);
            return;
        }

        let html = headerHtml + '<div class="sc-wrap">';

        if (variant === 'now' || variant === 'next') {
            const items = sc.items || [];
            if (!items.length) {
                html += '<div class="sc-empty">Nėra suplanuotų rungtynių</div>';
            } else {
                html += `<div class="sc-list ${variant}">`;
                for (const m of items) {
                    const live = m.in_progress ? ' live' : '';
                    html += `<div class="sc-card${live}">`
                        + meta([m.time, m.court, m.category].filter(Boolean))
                        + teams(m)
                        + (m.score ? `<div class="sc-score">${m.score}</div>` : '')
                        + '</div>';
                }
                html += '</div>';
            }
        } else {
            const groups = sc.groups || [];
            if (!groups.length) {
                html += '<div class="sc-empty">Nėra suplanuotų rungtynių</div>';
            } else {
                html += `<div class="sc-cols ${variant}">`;
                for (const g of groups) {
                    html += `<div class="sc-col"><div class="sc-col-head">${g.heading || '—'}</div>`;
                    for (const m of g.matches) {
                        const tag = variant === 'by_time' ? m.court : m.time;
                        html += `<div class="sc-row">${meta([tag, m.category].filter(Boolean))}${teams(m)}`
                            + (m.score ? `<div class="sc-score">${m.score}</div>` : '') + '</div>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            }
        }

        html += '</div>';
        stage.innerHTML = html;
        return;
    }

    // ── Draw (burtai) ───────────────────────────────────────────
    if ((d.window_type || 'groups') === 'draw') {
        const dr = d.draw || {};
        const slots = dr.slots || {};
        const nameAt = (k) => (slots[k] && slots[k].name) || null;
        // Animate only the slot that changed since the previous render (once).
        const animSlot = (dr.current && dr.current.slot !== window.__drawLastSlot) ? dr.current.slot : null;
        const cellClass = (k) => {
            const nm = nameAt(k);
            return [nm ? '' : 'empty', nm === 'BYE' ? 'bye' : '', k === animSlot ? 'just-in' : ''].filter(Boolean).join(' ');
        };

        let bodyHtml = '';
        if (dr.format === 'bracket') {
            // First-round match cards (the seeding sheet) — clean, professional,
            // scales to 8/16/32. Physical slot number shown as the seed position.
            const teamRow = (k) => `<div class="dteam ${cellClass(k)}"><span class="pos">${k}</span><span class="nm">${nameAt(k) || '—'}</span></div>`;
            bodyHtml = '<div class="draw-bracket">';
            (dr.board || []).forEach((pair, i) => {
                bodyHtml += `<div class="dmatch"><div class="dmatch-no">${i + 1}</div>`
                    + `<div class="dmatch-card">${teamRow(pair[0])}${teamRow(pair[1])}</div></div>`;
            });
            bodyHtml += '</div>';
        } else {
            const groups = dr.board || [];
            const cols = groups.length <= 2 ? groups.length : (groups.length <= 6 ? 3 : 4);
            bodyHtml = `<div class="draw-grid" style="grid-template-columns:repeat(${cols || 1},1fr)">`;
            for (const g of groups) {
                bodyHtml += `<div class="dg-card"><div class="gname">Grupė ${g.label}</div>`;
                g.slots.forEach((k, i) => {
                    bodyHtml += `<div class="dg-slot ${cellClass(k)}"><span class="pos">${i + 1}.</span><span class="nm">${nameAt(k) || '—'}</span></div>`;
                });
                bodyHtml += '</div>';
            }
            bodyHtml += '</div>';
        }

        const pool = (dr.pool || []).map((t) => `<span class="chip">${t.name}</span>`).join('');
        const poolHtml = `<div class="draw-pool"><div class="lbl">Liko traukti (${(dr.pool || []).length})</div><div class="chips">${pool}</div></div>`;

        const logo = dr.show_tournament && d.logo ? `<img src="${d.logo}" alt="">` : '';
        const tname = d.tournament_title || d.title || '';
        const group = dr.category || '';
        const headHtml = `<div class="draw-head"><div class="left">${logo}<div>`
            + `${tname ? `<div class="cat">${tname}</div>` : ''}`
            + `<div class="tt">${group || 'Burtai'}</div></div></div>`
            + `<div><span class="badge">BURTAI</span>${dr.status === 'done' ? '<span class="draw-done">Baigta</span>' : ''}</div></div>`;

        const allSponsors = dr.sponsors || [];
        const sponsHtml = allSponsors.length ? '<div class="draw-spons" id="draw-spons"></div>' : '';

        // The board is position:fixed, so it must live on <body> (outside #stage,
        // whose will-change:transform would otherwise become its containing block
        // and collapse it to a tiny box). Same trick as the results ticker.
        stage.innerHTML = '';
        const drawHost = document.getElementById('ov-draw') || (() => {
            const h = document.createElement('div'); h.id = 'ov-draw'; document.body.appendChild(h); return h;
        })();
        drawHost.innerHTML = `<div class="draw-stage draw-corner-${dr.camera_corner || 'bottom-right'}">${headHtml}<div class="draw-body">${bodyHtml}${poolHtml}</div>${sponsHtml}</div>`;

        // Sponsors: continuous marquee — equal tiles slide one-by-one to the
        // side, so the strip is always full. The set is duplicated so the loop
        // (translateX -50%) is seamless.
        clearInterval(window.__drawSpons);
        const spEl = document.getElementById('draw-spons');
        if (spEl && allSponsors.length) {
            const tile = (s) => `<div class="sp-tile"><img src="${s.logo}" alt=""></div>`;
            const set = allSponsors.map(tile).join('');
            const secs = Math.max(allSponsors.length, 4) * (dr.rotate_seconds || 5);
            spEl.innerHTML = `<div class="sp-track" style="animation-duration:${secs}s">${set}${set}</div>`;
        }

        // Remember which slot was last filled so the slide-in only fires once.
        window.__drawLastSlot = dr.current ? dr.current.slot : undefined;
        const _rv = document.getElementById('draw-reveal-host'); if (_rv) _rv.remove();
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
        html += `<div class="card"><div class="card-head"><span class="gname">${g.name || ''}</span>${g.segment ? `<span class="gseg">${g.segment}</span>` : ''}</div>`;
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
