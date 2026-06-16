<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overlay</title>
    <style>
        html, body { margin: 0; background: transparent; overflow: hidden;
            font-family: 'Inter', system-ui, sans-serif; color: #F5F5F0; }

        /* #root only positions; #stage handles the show/hide animation so the
           position transform (e.g. center) never conflicts with the motion. */
        #root { position: fixed; }
        .pos-bottom-left  { left: 40px; bottom: 40px; }
        .pos-bottom-right { right: 40px; bottom: 40px; }
        .pos-top-left     { left: 40px; top: 40px; }
        .pos-center       { left: 50%; top: 50%; transform: translate(-50%, -50%); }

        /* Entrance/exit: slide up + fade + slight scale. Driven by the .in class,
           toggled once per visibility change (not on every data poll). */
        #stage {
            opacity: 0;
            transform: translateY(28px) scale(.985);
            transition: opacity .5s cubic-bezier(.16, 1, .3, 1),
                        transform .5s cubic-bezier(.16, 1, .3, 1);
            will-change: opacity, transform;
        }
        #stage.in { opacity: 1; transform: none; }

        /* Staggered reveal of top-level blocks and cards/rounds — runs only
           while .intro is present (added on entrance, removed after it plays),
           so the 3s data refresh never replays it. */
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
    <div id="root" class="pos-bottom-left"><div id="stage"></div></div>
    <script>
        const DATA_URL = "{{ route('overlay.data', $overlay) }}";
        const POLL_MS  = 3000;
        const root  = document.getElementById('root');
        const stage = document.getElementById('stage');
        let shown = false;
        let introTimer = null;

        function render(d) { @yield('render_fn_body') }

        function playIntro() {
            stage.classList.add('intro');
            // double rAF so the transition starts from the hidden state
            requestAnimationFrame(() => requestAnimationFrame(() => stage.classList.add('in')));
            clearTimeout(introTimer);
            introTimer = setTimeout(() => stage.classList.remove('intro'), 1100);
        }

        async function tick() {
            try {
                const res = await fetch(DATA_URL, { cache: 'no-store' });
                const d = await res.json();

                if (!d.visible) {
                    if (shown) { stage.classList.remove('in'); shown = false; }
                    return;
                }

                root.className = 'pos-' + (d.position || 'bottom-left');
                render(d);

                if (!shown) { playIntro(); shown = true; }
            } catch (e) { /* keep last good frame on error */ }
        }
        tick();
        setInterval(tick, POLL_MS);
    </script>
    @yield('extra_script')
</body>
</html>
