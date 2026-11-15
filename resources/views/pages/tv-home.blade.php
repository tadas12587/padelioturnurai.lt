@php
    $months = ['saus.', 'vas.', 'kov.', 'bal.', 'geg.', 'birž.', 'liep.', 'rugp.', 'rugs.', 'spal.', 'lapkr.', 'gruod.'];
    $range = function ($t) use ($months) {
        if (! $t->date_start) return '';
        $s = $t->date_start; $e = $t->date_end ?? $s;
        $y = $s->year !== now()->year ? $s->year.' ' : '';
        return $s->month === $e->month
            ? $y.$months[$s->month - 1].' '.$s->day.($e->day !== $s->day ? '–'.$e->day : '')
            : $y.$months[$s->month - 1].' '.$s->day.' – '.$months[$e->month - 1].' '.$e->day;
    };
    $phase = ['live' => 'Vyksta dabar', 'upcoming' => 'Artėja', 'past' => 'Įvyko'];
    $first = true;
@endphp
<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>TV grafikas · Padelio turnyrai</title>
<meta name="theme-color" content="#0A2226">
<style>
:root { --ground: #0A2226; --surface: #0E2C30; --line: rgba(238,244,242,.16); --ink: #F2F7F5; --soft: #C3D8D3; --muted: #9DB7B1; --ball: #D9F45A; --ball-ink: #17230A; color-scheme: dark; }
* { box-sizing: border-box; margin: 0; }
html, body { min-height: 100%; background: var(--ground); color: var(--ink); }
body { font: 700 clamp(18px, 2.4vh, 40px)/1.3 Inter, system-ui, sans-serif; padding: 4vh 5vw; display: flex; flex-direction: column; gap: 3vh; }
header { display: flex; align-items: center; gap: .6em; }
header img { width: 2.2em; height: 2.2em; }
h1 { font-size: 1.8em; font-weight: 900; }
.hint { color: var(--soft); max-width: 50em; }
ul { list-style: none; padding: 0; display: grid; gap: 1.2em; max-width: 60em; }
li { background: var(--surface); border: .08em solid var(--line); border-radius: .5em; padding: .8em 1em; display: flex; flex-wrap: wrap; align-items: center; gap: .6em 1.2em; }
li.live { border-color: var(--ball); }
.what { flex: 1 1 18em; min-width: 0; }
.what small { display: block; color: var(--muted); font-size: .75em; text-transform: uppercase; letter-spacing: .06em; }
.live .what small { color: var(--ball); }
.what b { font-size: 1.15em; }
.btns { display: flex; gap: .6em; flex-wrap: wrap; }
/* big targets with an unmistakable focus ring — chosen with the TV remote's arrows */
a { color: var(--ink); text-decoration: none; padding: .55em 1em; border-radius: .4em; border: .1em solid var(--line); background: #123539; white-space: nowrap; }
a.main { background: var(--ball); color: var(--ball-ink); border-color: var(--ball); }
a:focus-visible, a:hover { outline: .22em solid var(--ink); outline-offset: .15em; }
a:focus { outline: .22em solid var(--ink); outline-offset: .15em; }
.empty { color: var(--soft); }
</style>
</head>
<body>
<header>
  <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('images/app-ball.png'))) }}" alt="">
  <h1>TV grafikas</h1>
</header>
<p class="hint">Pasirink turnyrą pultelio rodyklėmis ir paspausk OK. Turnyro dieną šis adresas atsidaro iškart.</p>
@if($tournaments->isEmpty())
  <p class="empty">Šiuo metu turnyrų su grafiku nėra.</p>
@else
<ul>
  @foreach($tournaments as $t)
    @php
        $id = $t->tournated_id;
        preg_match_all('/\d+/', (string) $t->tv_courts, $m);
        $halves = count($m[0]) > 6 ? array_chunk($m[0], (int) ceil(count($m[0]) / 2)) : [];
    @endphp
    <li class="{{ $t->tv_phase }}">
      <div class="what">
        <small>{{ $phase[$t->tv_phase] }} · {{ $range($t) }}</small>
        <b>{{ $t->translation('lt')?->title ?? $t->slug }}</b>
      </div>
      <div class="btns">
        @if($t->tv_phase === 'past')
          <a class="main" href="{{ route('schedule.tv', $id) }}?demo=1" @if($first) autofocus @endif>Bandomasis režimas</a>
        @else
          <a class="main" href="{{ route('schedule.tv', $id) }}" @if($first) autofocus @endif>Rodyti</a>
          @foreach($halves as $i => $h)
            <a href="{{ route('schedule.tv', $id) }}?kortai={{ implode(',', $h) }}">{{ $i + 1 }} TV: {{ implode(', ', $h) }} kortai</a>
          @endforeach
          <a href="{{ route('schedule.tv', $id) }}?demo=1">Bandomasis</a>
        @endif
      </div>
    </li>
    @php $first = false; @endphp
  @endforeach
</ul>
@endif
<script>
  // some TV browsers ignore autofocus: put the remote's focus on the first button
  addEventListener('load', () => { const a = document.querySelector('a[autofocus]'); if (a) a.focus(); });
</script>
</body>
</html>
