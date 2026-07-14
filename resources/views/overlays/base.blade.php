<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overlay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        html, body { margin: 0; background: transparent; overflow: hidden;
            font-family: 'Barlow', system-ui, sans-serif; color: var(--ov-text, #F5F5F0); }
        #root { position: fixed; }
        #scrim { position: fixed; inset: 0; opacity: 0; pointer-events: none;
            transition: opacity .5s cubic-bezier(.16,1,.3,1); z-index: -1; }
        .pos-top-left      { left: 40px; top: 40px; }
        .pos-top-center    { left: 50%; top: 40px; transform: translateX(-50%); }
        .pos-top-right     { right: 40px; top: 40px; }
        .pos-mid-left      { left: 40px; top: 50%; transform: translateY(-50%); }
        .pos-center        { left: 50%; top: 50%; transform: translate(-50%, -50%); }
        .pos-mid-right     { right: 40px; top: 50%; transform: translateY(-50%); }
        .pos-bottom-left   { left: 40px; bottom: 40px; }
        .pos-bottom-center { left: 50%; bottom: 40px; transform: translateX(-50%); }
        .pos-bottom-right  { right: 40px; bottom: 40px; }
        #stage {
            opacity: 0;
            transform: translateY(28px) scale(.985);
            transition: opacity .5s cubic-bezier(.16, 1, .3, 1),
                        transform .5s cubic-bezier(.16, 1, .3, 1);
            will-change: opacity, transform;
        }
        #stage.in { opacity: 1; transform: none; }
        @keyframes ov-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        #stage.intro > *,
        #stage.intro .card,
        #stage.intro .round { animation: ov-rise .5s cubic-bezier(.16, 1, .3, 1) both; }
        #stage.intro > *:nth-child(2)   { animation-delay: .07s; }
        #stage.intro > *:nth-child(3)   { animation-delay: .14s; }
        #stage.intro .card:nth-child(2),
        #stage.intro .round:nth-child(2) { animation-delay: .10s; }
        #stage.intro .card:nth-child(3),
        #stage.intro .round:nth-child(3) { animation-delay: .18s; }
        #stage.intro .card:nth-child(4),
        #stage.intro .round:nth-child(4) { animation-delay: .26s; }
        @yield('styles')
    </style>
</head>
<body>
    <div id="scrim"></div>
    <div id="root" class="pos-bottom-left"><div id="stage"></div></div>
    <script>
        const DATA_URL = "{{ route('overlay.data', $overlay) }}";
        const POLL_MS  = 3000;
        const root  = document.getElementById('root');
        const stage = document.getElementById('stage');
        const scrim = document.getElementById('scrim');
        let shown = false, introTimer = null, currentWindow = null, lastSig = null;

        function setColors(c) {
            if (!c) return;
            const r = document.documentElement.style;
            r.setProperty('--ov-bg', c.bg || '#111118');
            r.setProperty('--ov-text', c.text || '#F5F5F0');
            r.setProperty('--ov-accent', c.accent || '#C9A84C');
            r.setProperty('--ov-muted', c.muted || '#9CA3AF');
        }

        function applyScrim(d) {
            const s = d.scrim || {};
            if (s.enabled && s.opacity > 0) {
                scrim.style.background = (d.colors && d.colors.bg) || '#111118';
                scrim.style.opacity = Math.min(1, s.opacity / 100);
            } else {
                scrim.style.opacity = 0;
            }
        }

        // render one window's payload. keep=true means "don't wipe the other
        // windows' hosts" — used when compositing several windows at once.
        function render(d, keep) { @yield('render_fn_body') }

        const STAGE_TYPES = ['groups', 'group_standings', 'standings', 'bracket', 'schedule', 'results', 'next_match'];
        const removeHost = (id) => { const e = document.getElementById(id); if (e) e.remove(); };
        let lastSigs = {}, stageShown = false;

        function playIntro() {
            stage.classList.add('intro');
            requestAnimationFrame(() => requestAnimationFrame(() => stage.classList.add('in')));
            clearTimeout(introTimer);
            introTimer = setTimeout(() => stage.classList.remove('intro'), 1100);
        }

        function hideEverything() {
            if (stageShown) { stage.classList.remove('in'); stageShown = false; }
            scrim.style.opacity = 0;
            ['ov-ticker', 'draw-reveal-host', 'ov-draw', 'ov-spons', 'ov-h2h', 'ov-score', 'ov-pw'].forEach(removeHost);
            clearInterval(window.__drawSpons); clearInterval(window.__spTimer);
            window.__drawPoolRects = undefined; window.__drawHandledKey = undefined;
            stage.innerHTML = '';
            lastSigs = {}; shown = false; currentWindow = null;
        }

        function windowSig(w) {
            // exclude the H2H live score — the centre is patched separately below
            const h2 = w.h2h ? Object.assign({}, w.h2h, { live_score: null }) : w.h2h;
            return JSON.stringify({ t: w.window_type, g: w.groups, b: w.bracket, it: w.items,
                sc: w.schedule, sv: w.schedule_variant, nm: w.next_match, dr: w.draw, h2: h2,
                sc2: w.score, v: w.variant, cp: w.corner_position, csz: w.corner_size,
                pw: [w.main_logo, w.main_position, w.main_size, w.tile_size, w.gap],
                tt: w.tournament_title, ti: w.title, lg: w.logo, c: w.columns });
        }

        async function tick() {
            try {
                const res = await fetch(DATA_URL, { cache: 'no-store' });
                const d = await res.json();

                // Prefer the multi-window list; fall back to the flat shape.
                const wins = (d.windows && d.windows.length) ? d.windows : (d.visible ? [d] : []);
                if (!wins.length) { hideEverything(); return d; }

                setColors(d.colors);
                // scrim: on if any window enables it (take the strongest)
                const scr = wins.map(w => w.scrim).filter(s => s && s.enabled)
                    .sort((a, b) => (b.opacity || 0) - (a.opacity || 0))[0];
                applyScrim({ colors: d.colors, scrim: scr });
                root.className = 'pos-' + (d.position || 'bottom-left');

                // Reconcile: drop hosts for window types no longer active.
                const types = new Set(wins.map(w => w.window_type || 'groups'));
                if (!types.has('sponsors')) { removeHost('ov-spons'); clearInterval(window.__spTimer); }
                if (!types.has('h2h')) removeHost('ov-h2h');
                if (!types.has('score')) removeHost('ov-score');
                if (!types.has('photowall')) removeHost('ov-pw');
                if (!types.has('draw')) {
                    removeHost('ov-draw'); removeHost('draw-reveal-host');
                    clearInterval(window.__drawSpons);
                    window.__drawPoolRects = undefined; window.__drawHandledKey = undefined;
                }
                if (!types.has('results')) removeHost('ov-ticker');
                const hasStage = wins.some(w => STAGE_TYPES.includes(w.window_type || 'groups'));
                if (!hasStage && stageShown) { stage.innerHTML = ''; stage.classList.remove('in'); stageShown = false; }

                // Render each active window additively.
                const present = {};
                wins.forEach((w) => {
                    const id = w.window_id || (w.window_type || 'x');
                    present[id] = 1;
                    const sig = windowSig(w);
                    if (lastSigs[id] !== sig) {
                        render(w, true);
                        lastSigs[id] = sig;
                        if (STAGE_TYPES.includes(w.window_type || 'groups')) {
                            if (!stageShown) { playIntro(); stageShown = true; }
                        }
                    }
                    if ((w.window_type) === 'h2h' && window.__updH2hCenter) window.__updH2hCenter(w.h2h || {});
                });
                Object.keys(lastSigs).forEach(k => { if (!present[k]) delete lastSigs[k]; });
                shown = true;
                return d;
            } catch (e) { /* keep last good frame */ return null; }
        }

        // Self-scheduling poll: a draw window refreshes faster so the live
        // reveal feels snappy; everything else stays on POLL_MS.
        let pollTimer = null;
        function schedule(d) {
            const wins = (d && d.windows) ? d.windows : (d ? [d] : []);
            const fast = wins.some(w => (w.window_type) === 'draw');
            clearTimeout(pollTimer);
            pollTimer = setTimeout(loop, fast ? 500 : POLL_MS);
        }
        async function loop() { const d = await tick(); schedule(d); }
        loop();
    </script>
    @yield('extra_script')
</body>
</html>
