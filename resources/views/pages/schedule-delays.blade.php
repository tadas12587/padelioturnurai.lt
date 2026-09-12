<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vėlavimai · {{ $tournamentId }}</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
<style>
  :root {
    --ground: #0A2226; --surface: #0E2C30; --surface-2: #123539;
    --line: rgba(238,244,242,0.09); --ink: #EAF3F1; --ink-soft: #A9C4BE; --muted: #6E8B85;
    --ball: #D9F45A; --ball-ink: #17230a;
    --sans: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --display: 'Archivo', var(--sans);
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--ground); color: var(--ink); font-family: var(--sans); -webkit-font-smoothing: antialiased; }
  .wrap { max-width: 480px; margin: 0 auto; padding: 24px 16px 60px; }
  h1 { font-family: var(--display); font-size: 1.15rem; margin: 0 0 4px; }
  p.hint { color: var(--ink-soft); font-size: 0.85rem; margin: 0 0 24px; line-height: 1.5; }
  .saved {
    background: rgba(217,244,90,0.14); border: 1px solid var(--ball); color: var(--ball);
    border-radius: 10px; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 20px;
  }
  .row {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 12px;
    padding: 14px 16px; margin-bottom: 10px;
  }
  .row label { font-weight: 600; font-size: 0.95rem; }
  .row .field { display: flex; align-items: center; gap: 8px; }
  .row input[type=number] {
    width: 72px; background: var(--surface-2); border: 1px solid var(--line); color: var(--ink);
    font-size: 1rem; font-family: var(--sans); border-radius: 8px; padding: 8px 10px; text-align: center;
  }
  .row input[type=number]:focus { outline: none; border-color: var(--ball); }
  .row .unit { color: var(--muted); font-size: 0.82rem; }
  button {
    width: 100%; margin-top: 14px; background: var(--ball); color: var(--ball-ink); border: none;
    border-radius: 999px; padding: 14px; font-family: var(--display); font-weight: 700; font-size: 0.95rem;
    cursor: pointer;
  }
  button:active { opacity: 0.85; }
  .empty { color: var(--muted); font-size: 0.9rem; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Kortų vėlavimai</h1>
  <p class="hint">
    Nustatytas vėlavimas rodomas TIK dar nesužaistiems mačams (jau sužaistų mačų laikas
    visada rodomas tikras). 0 min = kortas grįžo prie grafiko.
  </p>

  @if (session('saved'))
    <div class="saved">Išsaugota.</div>
  @endif

  <form method="POST" action="{{ route('schedule.delays.save', ['tournament' => $tournamentId]) }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    @forelse ($courts as $court)
      @php $current = $delays[$court] ?? 0; @endphp
      <div class="row">
        <label for="delay-{{ $loop->index }}">{{ $court }}</label>
        <div class="field">
          <input type="number" min="0" max="240" step="5"
                 id="delay-{{ $loop->index }}"
                 name="delays[{{ $court }}]"
                 value="{{ $current }}">
          <span class="unit">min</span>
        </div>
      </div>
    @empty
      <p class="empty">Kortų dar nerasta — palauk, kol atsiras mačų.</p>
    @endforelse

    <button type="submit">Išsaugoti</button>
  </form>
</div>
</body>
</html>
