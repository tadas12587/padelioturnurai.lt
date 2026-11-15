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
* { box-sizing: border-box; margin: 0; }
html, body { min-height: 100%; background: #0A2226; color: #F2F7F5; }
body { font: 700 24px/1.3 Inter, Arial, sans-serif; font-size: 2.4vh; padding: 4vh 5vw; }
body > * + * { margin-top: 3vh; }
header { display: -webkit-flex; display: flex; -webkit-align-items: center; align-items: center; }
header img { margin-right: .6em; }
header img { width: 2.2em; height: 2.2em; }
h1 { font-size: 1.8em; font-weight: 900; }
.hint { color: #C3D8D3; max-width: 50em; }
ul { list-style: none; padding: 0; max-width: 60em; }
li + li { margin-top: 1.2em; }
li { background: #0E2C30; border: .08em solid rgba(238,244,242,.16); border-radius: .5em; padding: .8em 1em; display: -webkit-flex; display: flex; -webkit-flex-wrap: wrap; flex-wrap: wrap; -webkit-align-items: center; align-items: center; }
li.live { border-color: #D9F45A; }
.what { -webkit-flex: 1 1 18em; flex: 1 1 18em; min-width: 0; margin-right: 1.2em; }
.what small { display: block; color: #9DB7B1; font-size: .75em; text-transform: uppercase; letter-spacing: .06em; }
.live .what small { color: #D9F45A; }
.what b { font-size: 1.15em; }
.btns { margin: .3em 0; }
.btns a { display: inline-block; margin: .3em .6em .3em 0; }
/* big targets with an unmistakable focus ring — chosen with the TV remote's arrows */
a { color: #F2F7F5; text-decoration: none; padding: .55em 1em; border-radius: .4em; border: .1em solid rgba(238,244,242,.16); background: #123539; white-space: nowrap; }
a.main { background: #D9F45A; color: #17230A; border-color: #D9F45A; }
a:hover { outline: .22em solid #F2F7F5; outline-offset: .15em; }
a:focus { outline: .22em solid #F2F7F5; outline-offset: .15em; }
.empty { color: #C3D8D3; }
</style>
</head>
<body>
<header>
  <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('images/app-ball.png'))) }}" alt="">
  <h1>TV grafikas</h1>
</header>
<p class="hint">Pasirink pultelio rodyklėmis ir paspausk OK. Turnyro dieną: padelioturnyrai.lt/tv/1, /tv/2 … atidaro atitinkamą ekraną iškart.</p>
@if($tournaments->isEmpty())
  <p class="empty">Šiuo metu turnyrų su grafiku nėra.</p>
@else
<ul>
  @foreach($tournaments as $t)
    @php
        $id = $t->tournated_id;
        $screens = $t->tvScreens();
        $names = $screens ? $courtNames($t) : [];
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
          <a class="main" href="{{ route('schedule.tv', $id) }}" @if($first) autofocus @endif>{{ $screens ? 'Visi kortai' : 'Rodyti' }}</a>
          @foreach($screens as $i => $sc)
            <a href="{{ route('schedule.tv', $id) }}?ekranas={{ $i + 1 }}">{{ $i + 1 }}: {{ $sc['name'] !== '' ? $sc['name'] : implode(', ', array_map(fn ($c) => $names[$c] ?? $c, $sc['courts'])) }}</a>
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
