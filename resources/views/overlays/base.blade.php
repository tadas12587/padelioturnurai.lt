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

        function render(d) { @yield('render_fn_body') }

        function playIntro() {
            stage.classList.add('intro');
            requestAnimationFrame(() => requestAnimationFrame(() => stage.classList.add('in')));
            clearTimeout(introTimer);
            introTimer = setTimeout(() => stage.classList.remove('intro'), 1100);
        }

        async function tick() {
            try {
                const res = await fetch(DATA_URL, { cache: 'no-store' });
                const d = await res.json();

                if (!d.visible) {
                    if (shown) { stage.classList.remove('in'); shown = false; currentWindow = null; }
                    scrim.style.opacity = 0;
                    const t = document.getElementById('ov-ticker'); if (t) t.remove();
                    const rv = document.getElementById('draw-reveal-host'); if (rv) rv.remove();
                    const dh = document.getElementById('ov-draw'); if (dh) dh.remove();
                    const sp = document.getElementById('ov-spons'); if (sp) sp.remove();
                    const hh = document.getElementById('ov-h2h'); if (hh) hh.remove();
                    clearInterval(window.__drawSpons); clearInterval(window.__spTimer);
                    window.__drawPoolRects = undefined; window.__drawHandledKey = undefined;
                    return d;
                }

                setColors(d.colors);
                applyScrim(d);
                root.className = 'pos-' + (d.position || 'bottom-left');

                const sig = JSON.stringify({ w: d.window_id, g: d.groups, b: d.bracket,
                    it: d.items, sc: d.schedule, sv: d.schedule_variant, nm: d.next_match,
                    dr: d.draw, h2: d.h2h, v: d.variant, cp: d.corner_position, csz: d.corner_size,
                    tt: d.tournament_title, ti: d.title, lg: d.logo, c: d.columns });

                if (!shown) {
                    render(d); playIntro(); shown = true; currentWindow = d.window_id; lastSig = sig;
                } else if (d.window_id !== currentWindow) {
                    stage.classList.remove('in'); currentWindow = d.window_id; lastSig = sig;
                    setTimeout(() => { render(d); playIntro(); }, 420);
                } else if (sig !== lastSig) {
                    lastSig = sig; render(d);
                }
                return d;
            } catch (e) { /* keep last good frame */ return null; }
        }

        // Self-scheduling poll: the draw window refreshes faster so the live
        // reveal feels snappy; everything else stays on POLL_MS.
        let pollTimer = null;
        function schedule(d) {
            const ms = (d && d.window_type === 'draw') ? 500 : POLL_MS;
            clearTimeout(pollTimer);
            pollTimer = setTimeout(loop, ms);
        }
        async function loop() { const d = await tick(); schedule(d); }
        loop();
    </script>
    @yield('extra_script')
</body>
</html>
