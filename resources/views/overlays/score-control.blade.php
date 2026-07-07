<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rezultatas — {{ $overlay->name }}</title>
    <style>
        :root { --bg:#0f1014; --card:#1a1c22; --line:#2a2d36; --txt:#f2f3f5; --muted:#9aa0ad; --accent:#C9A84C; }
        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        html,body { margin:0; background:var(--bg); color:var(--txt); font-family:system-ui,-apple-system,'Segoe UI',sans-serif; }
        .wrap { max-width:560px; margin:0 auto; padding:12px; }
        .tabs { display:flex; gap:8px; margin-bottom:14px; }
        .tab { flex:1; padding:12px; border:1px solid var(--line); border-radius:10px; background:var(--card); color:var(--txt);
            font-size:16px; font-weight:600; text-align:center; }
        .tab.on { border-color:var(--accent); background:rgba(201,168,76,.14); color:var(--accent); }
        .teams { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .tcard { padding:14px; border:1px solid var(--line); border-radius:14px; background:var(--card); text-align:center; }
        .tcard.serve { border-color:var(--accent); }
        .tname { font-weight:600; font-size:15px; min-height:2.6em; display:flex; align-items:center; justify-content:center; }
        .pt { font-size:52px; font-weight:800; font-family:'Segoe UI',sans-serif; line-height:1; margin:6px 0; }
        .sub { font-size:13px; color:var(--muted); margin-bottom:10px; }
        .plus { width:100%; padding:20px; font-size:30px; font-weight:800; border:none; border-radius:12px; background:#1f6b3a; color:#fff; }
        .plus:active { transform:scale(.98); }
        .serveb { width:100%; margin-top:8px; padding:10px; font-size:13px; border:1px solid var(--line); border-radius:10px; background:transparent; color:var(--muted); }
        .rowbtns { display:flex; gap:8px; margin-top:14px; }
        .rowbtns button { flex:1; padding:14px; font-size:15px; font-weight:600; border:1px solid var(--line); border-radius:10px; background:var(--card); color:var(--txt); }
        .st { text-align:center; margin-top:12px; color:var(--accent); font-weight:600; min-height:1.2em; }
        .fx { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; max-height:40vh; overflow:auto; }
        .fx button { text-align:left; padding:12px; border:1px solid var(--line); border-radius:10px; background:var(--card); color:var(--txt); font-size:14px; }
        .fx button.on { border-color:var(--accent); background:rgba(201,168,76,.12); }
        .field { margin-bottom:10px; }
        .field label { display:block; font-size:13px; color:var(--muted); margin-bottom:4px; }
        .field input, .field select { width:100%; padding:10px; border:1px solid var(--line); border-radius:8px; background:#141821; color:var(--txt); font-size:15px; }
        .field.row { display:flex; align-items:center; justify-content:space-between; }
        .field.row label { margin:0; }
        .save { width:100%; padding:14px; margin-top:6px; font-size:16px; font-weight:600; border:none; border-radius:10px; background:var(--accent); color:#0A0A0F; }
        .hide { display:none; }
        .muted { color:var(--muted); font-size:13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="tabs">
        <div class="tab on" id="tab-score" onclick="showTab('score')">Rezultatas</div>
        <div class="tab" id="tab-settings" onclick="showTab('settings')">Nustatymai</div>
    </div>

    <div id="pane-score">
        <div id="score-body"><div class="muted">Kraunama…</div></div>
        <div class="rowbtns">
            <button onclick="act({action:'undo'})">− Atšaukti</button>
            <button onclick="if(confirm('Pradėti iš naujo?'))act({action:'reset'})">Iš naujo</button>
        </div>
        <div class="st" id="status"></div>
    </div>

    <div id="pane-settings" class="hide">
        <div class="muted" style="margin-bottom:6px;">Kas žaidžia (pasirink rungtynes)</div>
        <div class="field"><input type="text" id="fx-search" placeholder="Ieškoti žaidėjo…" oninput="renderFixtures()"></div>
        <div style="display:flex; gap:8px; margin-bottom:10px;">
            <select id="fx-court" onchange="renderFixtures()" style="flex:1; padding:10px; border:1px solid var(--line); border-radius:8px; background:#141821; color:var(--txt);"><option value="">Visi kortai</option></select>
            <select id="fx-level" onchange="renderFixtures()" style="flex:1; padding:10px; border:1px solid var(--line); border-radius:8px; background:#141821; color:var(--txt);"><option value="">Visi lygiai</option></select>
        </div>
        <div class="fx" id="fixtures"></div>
        <div class="muted" style="margin:10px 0 6px;">Taisyklės</div>
        <div class="field"><label>Geimų sete</label><input type="number" id="r_gps" min="1"></div>
        <div class="field"><label>Tiebreak prie (geimų)</label><input type="number" id="r_tba"></div>
        <div class="field"><label>Laimėtų setų</label><input type="number" id="r_stw" min="1"></div>
        <div class="field row"><label>Tiebreak sete</label><input type="checkbox" id="r_tb"></div>
        <div class="field"><label>Tiebreak iki</label><input type="number" id="r_tbt"></div>
        <div class="field row"><label>Lemiamas – super tiebreak</label><input type="checkbox" id="r_stb"></div>
        <div class="field"><label>Super tiebreak iki</label><input type="number" id="r_stbt"></div>
        <div class="field"><label>Lygiosios (40–40)</label>
            <select id="r_deuce"><option value="advantage">Pranašumas</option><option value="golden">Auksinis taškas</option><option value="star">STAR</option></select>
        </div>
        <button class="save" onclick="saveRules()">Išsaugoti taisykles</button>
        <button class="save" style="background:#211519;color:#f3b0b0;margin-top:8px;" onclick="act({action:'stop'})">Sustabdyti (OBS)</button>
    </div>
</div>

<script>
    const URL = @json(url('/overlay/'.$overlay->token.'/score'));
    let data = null, tab = 'score';

    function showTab(t) {
        tab = t;
        document.getElementById('pane-score').classList.toggle('hide', t !== 'score');
        document.getElementById('pane-settings').classList.toggle('hide', t !== 'settings');
        document.getElementById('tab-score').classList.toggle('on', t === 'score');
        document.getElementById('tab-settings').classList.toggle('on', t === 'settings');
    }

    async function act(payload) {
        try {
            const r = await fetch(URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload || { action: 'state' }) });
            data = await r.json();
            render();
        } catch (e) { /* keep */ }
    }

    function render() {
        if (!data) return;
        const c = data.card || {};
        const body = document.getElementById('score-body');
        if (!c.found) {
            body.innerHTML = '<div class="muted">Pasirink rungtynes skirtuke „Nustatymai".</div>';
        } else {
            const t = (i) => {
                const tm = c.teams[i];
                const sets = (tm.sets || []).join(' ');
                return `<div class="tcard${tm.serving ? ' serve' : ''}">
                    <div class="tname">${tm.name}</div>
                    <div class="pt">${tm.point}</div>
                    <div class="sub">Geimai ${tm.games}${sets ? ' · Setai ' + sets : ''}</div>
                    <button class="plus" onclick="act({action:'point',team:${i}})">+</button>
                    <button class="serveb" onclick="act({action:'serve',team:${i}})">Servas šiai</button>
                </div>`;
            };
            body.innerHTML = `<div class="teams">${t(0)}${t(1)}</div>`;
        }
        let st = '';
        if (data.status === 'finished') st = 'Mačas baigtas';
        else if (data.super_tiebreak) st = 'Super tiebreak';
        else if (data.tiebreak) st = 'Tiebreak';
        document.getElementById('status').textContent = st;

        populateFilters();
        renderFixtures();

        // rules (only fill when not focused, to avoid clobbering typing)
        if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'SELECT') {
            const r = data.rules || {};
            g('r_gps').value = r.games_per_set; g('r_tba').value = r.tiebreak_at; g('r_stw').value = r.sets_to_win;
            g('r_tb').checked = !!r.tiebreak; g('r_tbt').value = r.tiebreak_to; g('r_stb').checked = !!r.super_tb;
            g('r_stbt').value = r.super_tb_to; g('r_deuce').value = r.deuce_mode;
        }
    }
    const g = (id) => document.getElementById(id);

    function fillSelect(sel, values, allLabel) {
        const cur = sel.value;
        const opts = ['<option value="">' + allLabel + '</option>']
            .concat(values.map((v) => `<option value="${v}">${v}</option>`));
        sel.innerHTML = opts.join('');
        if (values.includes(cur)) sel.value = cur;
    }
    function populateFilters() {
        const fx = data.fixtures || [];
        const courts = [...new Set(fx.map((m) => m.court).filter(Boolean))].sort();
        const levels = [...new Set(fx.map((m) => m.cat).filter(Boolean))].sort();
        fillSelect(g('fx-court'), courts, 'Visi kortai');
        fillSelect(g('fx-level'), levels, 'Visi lygiai');
    }
    function renderFixtures() {
        const fx = document.getElementById('fixtures');
        const q = (g('fx-search').value || '').toLowerCase().trim();
        const court = g('fx-court').value, level = g('fx-level').value;
        const list = (data.fixtures || []).filter((m) => {
            if (court && m.court !== court) return false;
            if (level && m.cat !== level) return false;
            if (q && !((m.t1 + ' ' + m.t2).toLowerCase().includes(q))) return false;
            return true;
        });
        fx.innerHTML = list.map((m) =>
            `<button class="${String(data.match_id) === String(m.id) ? 'on' : ''}" onclick="act({action:'select',match_id:${JSON.stringify(m.id)}})">${m.t1 || 'TBD'} <span class="muted">vs</span> ${m.t2 || 'TBD'} <span class="muted">${[m.time, m.court, m.cat].filter(Boolean).join(' · ')}</span></button>`
        ).join('') || '<div class="muted">Nėra atitinkančių rungtynių.</div>';
    }

    function saveRules() {
        act({
            action: 'rules',
            score_games_per_set: g('r_gps').value, score_tiebreak_at: g('r_tba').value, score_sets_to_win: g('r_stw').value,
            score_tiebreak: g('r_tb').checked ? 1 : 0, score_tiebreak_to: g('r_tbt').value,
            score_super_tb: g('r_stb').checked ? 1 : 0, score_super_tb_to: g('r_stbt').value, score_deuce_mode: g('r_deuce').value,
        });
    }

    act({ action: 'state' });
    setInterval(() => act({ action: 'state' }), 2500);
</script>
</body>
</html>
