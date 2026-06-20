<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valdymas — {{ $overlay->name }}</title>
    <style>
        :root { --bg:#0f1014; --card:#1a1c22; --line:#2a2d36; --txt:#f2f3f5; --muted:#9aa0ad; --accent:#C9A84C; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: var(--bg); color: var(--txt);
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .wrap { padding: 14px; max-width: 560px; margin: 0 auto; }
        .hd { display: flex; align-items: baseline; gap: 8px; margin-bottom: 14px; }
        .hd .nm { font-size: 18px; font-weight: 600; }
        .hd .dot { width: 9px; height: 9px; border-radius: 50%; background: #555; }
        .hd .dot.live { background: var(--accent); box-shadow: 0 0 10px var(--accent); }
        .wins { display: flex; flex-direction: column; gap: 10px; }
        .win { display: flex; align-items: center; justify-content: space-between; gap: 10px;
            width: 100%; padding: 16px 18px; border: 1px solid var(--line); border-radius: 12px;
            background: var(--card); color: var(--txt); font-size: 17px; font-weight: 500;
            text-align: left; cursor: pointer; -webkit-tap-highlight-color: transparent; }
        .win:active { transform: scale(.99); }
        .win .ty { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
        .win.on { border-color: var(--accent); background: rgba(201,168,76,.12); }
        .win.on .ty { color: var(--accent); }
        .win .live-tag { color: var(--accent); font-weight: 700; font-size: 13px; }
        .stop { width: 100%; margin-top: 16px; padding: 16px; border: 1px solid var(--line);
            border-radius: 12px; background: #211519; color: #f3b0b0; font-size: 16px; font-weight: 600; cursor: pointer; }
        .empty { color: var(--muted); padding: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hd">
            <span class="dot" id="dot"></span>
            <span class="nm">{{ $overlay->name }}</span>
        </div>

        <div class="wins">
            @forelse($overlay->windows ?? [] as $w)
                <button class="win" data-id="{{ $w['id'] }}" onclick="play('{{ $w['id'] }}')">
                    <span>{{ $w['name'] ?? 'Langas' }}<br><span class="ty">{{ $w['type'] ?? 'groups' }}</span></span>
                    <span class="live-tag" data-live></span>
                </button>
            @empty
                <div class="empty">Šis overlay neturi langų. Sukurk juos admin redagavimo lange.</div>
            @endforelse
        </div>

        <button class="stop" onclick="stop()">■ Sustabdyti</button>
    </div>

    <script>
        const ACTION = @json(url('/overlay/'.$overlay->token.'/control'));
        const DATA   = @json(url('/overlay/'.$overlay->token.'/data'));
        let active = null;

        async function post(body) {
            try {
                await fetch(ACTION, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            } catch (e) { /* ignore */ }
            refresh();
        }
        function play(id) { active = id; paint(); post({ action: 'play', window_id: id }); }
        function stop()   { active = null; paint(); post({ action: 'stop' }); }

        function paint() {
            document.querySelectorAll('.win').forEach((b) => {
                const on = b.dataset.id === active;
                b.classList.toggle('on', on);
                b.querySelector('[data-live]').textContent = on ? '● GYVAI' : '';
            });
            document.getElementById('dot').classList.toggle('live', !!active);
        }

        async function refresh() {
            try {
                const r = await fetch(DATA, { cache: 'no-store' });
                const d = await r.json();
                active = d.visible ? d.window_id : null;
                paint();
            } catch (e) { /* keep last */ }
        }
        refresh();
        setInterval(refresh, 2000);
    </script>
</body>
</html>
