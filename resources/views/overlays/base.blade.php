<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overlay</title>
    <style>
        html, body { margin: 0; background: transparent; overflow: hidden;
            font-family: 'Inter', system-ui, sans-serif; color: #F5F5F0; }
        #root { position: fixed; opacity: 0; transition: opacity .4s ease; }
        #root.visible { opacity: 1; }
        .pos-bottom-left  { left: 40px; bottom: 40px; }
        .pos-bottom-right { right: 40px; bottom: 40px; }
        .pos-top-left     { left: 40px; top: 40px; }
        .pos-center       { left: 50%; top: 50%; transform: translate(-50%,-50%); }
        @yield('styles')
    </style>
</head>
<body>
    <div id="root"></div>
    <script>
        const DATA_URL = "{{ route('overlay.data', $overlay) }}";
        const POLL_MS  = 3000;
        const root = document.getElementById('root');

        function render(d) { @yield('render_fn_body') }

        async function tick() {
            try {
                const res = await fetch(DATA_URL, { cache: 'no-store' });
                const d = await res.json();
                if (!d.visible) { root.classList.remove('visible'); return; }
                root.className = 'pos-' + (d.position || 'bottom-left');
                render(d);
                requestAnimationFrame(() => root.classList.add('visible'));
            } catch (e) { /* keep last good frame on error */ }
        }
        tick();
        setInterval(tick, POLL_MS);
    </script>
    @yield('extra_script')
</body>
</html>
