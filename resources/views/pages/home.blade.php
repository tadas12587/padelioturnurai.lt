@extends('layouts.app')

@section('title', 'Padelio Turnyrai - ' . __('messages.nav_home'))

@section('og_title', 'Padelio Turnyrai — Oficialus Lietuvos Padelio Turnyrai')
@section('og_description', 'Lietuvos padelio turnyrai – registracija, tvarkaraštis, rezultatai, reitingai ir naujienos viename puslapyje. Tapkite dalimi!')
@if($heroPhotos->isNotEmpty())
    @section('og_image', $heroPhotos->first())
@endif

@section('content')

@php
$locale = app()->getLocale();

// Tournament titles are often typed in ALL CAPS in the admin; show them in
// sentence case but keep Roman numerals (III etapas) upper-case.
$niceTitle = function (?string $title) {
    $title = trim((string) $title);
    if ($title === '' || $title !== mb_strtoupper($title)) return $title;
    $s = mb_strtolower($title);
    $s = mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    return preg_replace_callback('/\b(i{1,3}|iv|vi{0,3}|ix|x)\b/u', fn($m) => mb_strtoupper($m[0]), $s);
};

// "Lapkričio 27–28 d." / "2025 m. liepos 26–27 d." / "Nov 27–28, 2025"
$dateRange = function ($start, $end) use ($locale) {
    $end = $end ?? $start;
    $showYear = $start->year !== now('Europe/Vilnius')->year;
    if ($locale === 'en') {
        $s = $start->format('M j');
        $e = $start->isSameMonth($end) ? $end->format('j') : $end->format('M j');
        $txt = $start->isSameDay($end) ? $s : $s . '–' . $e;
        return $txt . ($showYear ? ', ' . $start->year : '');
    }
    $m = ['sausio', 'vasario', 'kovo', 'balandžio', 'gegužės', 'birželio', 'liepos', 'rugpjūčio', 'rugsėjo', 'spalio', 'lapkričio', 'gruodžio'];
    $sm = $m[$start->month - 1];
    if ($start->isSameDay($end))       $txt = "$sm {$start->day} d.";
    elseif ($start->isSameMonth($end)) $txt = "$sm {$start->day}–{$end->day} d.";
    else                               $txt = "$sm {$start->day} d. – " . $m[$end->month - 1] . " {$end->day} d.";
    return $showYear ? "{$start->year} m. $txt" : mb_strtoupper(mb_substr($txt, 0, 1)) . mb_substr($txt, 1);
};

$next      = $nextTournament;
$nextTrans = $next?->translation($locale);
$nextTitle = $niceTitle($nextTrans?->title ?? $next?->slug);
$hasSchedule = $next && $next->tournated_id && $next->show_schedule;
$canRegister = $next && $next->registration_active && $next->registration_url;
$heroImage = $heroPhotos->first() ?? ($next?->cover_image ? Storage::url($next->cover_image) : null);
$startsAt  = $next ? \Illuminate\Support\Carbon::parse($next->date_start->format('Y-m-d') . ' 00:00', 'Europe/Vilnius') : null;
$left      = $startsAt ? max(0, now()->diffInMinutes($startsAt, false)) : 0;
@endphp

{{-- Structured data: Organization + WebSite --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id'   => url('/') . '#organization',
            'name'  => 'Padelio Turnyrai',
            'url'   => url('/'),
        ],
        [
            '@type'     => 'WebSite',
            '@id'       => url('/') . '#website',
            'name'      => 'Padelio Turnyrai',
            'url'       => url('/'),
            'inLanguage' => $locale === 'en' ? 'en' : 'lt',
            'publisher' => ['@id' => url('/') . '#organization'],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>

@push('styles')
<style>
    .home-hero-img { filter: grayscale(1) brightness(.62); }
    .home-hero-shade { background: linear-gradient(to bottom, rgba(10,10,15,.55) 0%, rgba(10,10,15,.15) 35%, rgba(10,10,15,.82) 72%, #0A0A0F 100%); }
    .home-title { text-wrap: balance; letter-spacing: -0.02em; }
    .tabular { font-variant-numeric: tabular-nums; }

    /* The page's one authored moment: the hero copy settles in once. */
    @media (prefers-reduced-motion: no-preference) {
        .hero-in > * {
            animation: hero-in 700ms cubic-bezier(.16, 1, .3, 1) backwards;
            animation-delay: calc(var(--i, 0) * 70ms + 120ms);
        }
        .live-dot::after { animation: live-ring 1.6s ease-out 3; }
    }
    @keyframes hero-in { from { opacity: 0; translate: 0 14px; } }
    .live-dot { position: relative; }
    .live-dot::after { content: ""; position: absolute; inset: 0; border-radius: 9999px; background: currentColor; opacity: 0; }
    @keyframes live-ring { from { opacity: .55; scale: 1; } to { opacity: 0; scale: 2.6; } }
</style>
@endpush

{{-- ===================== HERO ===================== --}}
<section class="relative isolate overflow-hidden min-h-[640px] md:min-h-[88vh] flex items-end">
    @if($heroImage)
        <img src="{{ $heroImage }}" alt="" fetchpriority="high"
             class="home-hero-img absolute inset-0 -z-20 w-full h-full object-cover object-center">
    @endif
    <div class="home-hero-shade absolute inset-0 -z-10"></div>

    <div class="w-full max-w-6xl mx-auto px-4 pt-32 pb-12 md:pb-20">
        @if($next)
            <div class="hero-in max-w-3xl">
                <h1 class="home-title text-4xl sm:text-5xl md:text-6xl font-black text-white leading-[1.05] mb-5" style="--i:0">
                    {{ $nextTitle }}
                </h1>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-gray-200 text-base mb-7" style="--i:1">
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-[18px] h-[18px] text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        {{ $dateRange($next->date_start, $next->date_end) }}
                    </span>
                    @if($next->location)
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-[18px] h-[18px] text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        {{ $next->location }}
                    </span>
                    @endif
                    @if($isLive)
                        <span class="inline-flex items-center gap-2 px-3 py-1 border border-gold/60 text-gold text-sm font-bold">
                            <span class="live-dot w-2 h-2 rounded-full bg-gold text-gold"></span>{{ __('messages.home_live_now') }}
                        </span>
                    @elseif($canRegister)
                        <span class="px-3 py-1 border border-gold/60 text-gold text-sm font-bold">{{ __('messages.tournament_status_active') }}</span>
                    @endif
                </div>

                @unless($isLive)
                <div class="mb-8" style="--i:2">
                    <p class="text-gray-300 text-sm mb-2">{{ __('messages.home_starts_in') }}</p>
                    <div class="flex gap-2" data-countdown="{{ $startsAt->toIso8601String() }}">
                        @foreach(['d' => [intdiv($left, 1440), 'home_days'], 'h' => [intdiv($left % 1440, 60), 'home_hours'], 'm' => [$left % 60, 'home_minutes']] as $unit => [$val, $label])
                        <div class="w-[72px] py-2 text-center border border-gold/40 bg-dark/50">
                            <b class="tabular block text-2xl font-black text-white" data-unit="{{ $unit }}">{{ str_pad($val, 2, '0', STR_PAD_LEFT) }}</b>
                            <span class="text-xs text-gray-400">{{ __('messages.' . $label) }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endunless

                <div class="flex flex-col sm:flex-row gap-3" style="--i:3">
                    @if($isLive && $hasSchedule)
                        <a href="{{ route('schedule.show', $next->tournated_id) }}"
                           class="px-8 py-4 bg-gold text-dark font-bold hover:bg-gold-light transition-colors text-center text-base">
                            {{ __('messages.home_open_schedule') }} &rarr;
                        </a>
                    @elseif($canRegister)
                        <a href="{{ $next->registration_url }}" target="_blank" rel="noopener"
                           class="px-8 py-4 bg-gold text-dark font-bold hover:bg-gold-light transition-colors text-center text-base">
                            {{ __('messages.register_btn') }} &rarr;
                        </a>
                    @endif
                    <a href="{{ lroute('tournament.show', ['slug' => $next->slug]) }}"
                       class="px-8 py-4 border border-gold text-gold font-bold hover:bg-gold hover:text-dark transition-colors text-center text-base">
                        {{ __('messages.learn_more') }}
                    </a>
                    @if(!$canRegister && !$isLive && $next->notify_enabled)
                        @include('partials.notify-btn', ['tournamentId' => $next->id, 'tournamentName' => $nextTitle, 'compact' => false])
                    @endif
                </div>
            </div>
        @else
            <div class="hero-in max-w-3xl">
                <h1 class="home-title text-5xl md:text-7xl font-black text-white leading-none mb-5" style="--i:0">
                    Padelio <span class="text-gold">turnyrai</span>
                </h1>
                <p class="text-xl md:text-2xl text-gray-200 mb-8" style="--i:1">{{ __('messages.hero_tagline') }}</p>
                <div class="flex flex-col sm:flex-row gap-3" style="--i:2">
                    <a href="{{ lroute('tournaments') }}" class="px-8 py-4 bg-gold text-dark font-bold hover:bg-gold-light transition-colors text-center">{{ __('messages.all_tournaments') }}</a>
                    <a href="{{ lroute('contact') }}" class="px-8 py-4 border border-gold text-gold font-bold hover:bg-gold hover:text-dark transition-colors text-center">{{ __('messages.nav_contact') }}</a>
                </div>
            </div>
        @endif
    </div>
</section>

{{-- ===================== STATS ===================== --}}
@if($stats->count() > 0)
<section class="bg-dark border-t border-dark-border">
    <h2 class="sr-only">{{ __('messages.stats_section_title') }}</h2>
    <dl class="max-w-6xl mx-auto px-4 py-10 grid grid-cols-3 gap-4">
        @foreach($stats as $stat)
        <div>
            <dd class="tabular text-3xl md:text-5xl font-black text-white">{{ number_format($stat->display_value, 0, ',', ' ') }}</dd>
            <dt class="text-sm text-gray-400 mt-1">{{ $locale === 'en' ? $stat->label_en : $stat->label_lt }}</dt>
        </div>
        @endforeach
    </dl>
</section>
@endif

{{-- ===================== PAST TOURNAMENTS ===================== --}}
@if($pastTournaments->count() > 0)
<section class="bg-dark border-t border-dark-border">
    <div class="max-w-6xl mx-auto px-4 py-14 md:py-20">
        <div class="flex items-baseline justify-between gap-4 mb-6">
            <h2 class="text-2xl md:text-3xl font-black text-white">{{ __('messages.home_past_tournaments') }}</h2>
            <a href="{{ lroute('tournaments') }}" class="text-gold font-semibold hover:text-gold-light transition-colors whitespace-nowrap">{{ __('messages.all_tournaments') }} &rarr;</a>
        </div>
        <ul class="grid md:grid-cols-2 gap-x-10">
            @foreach($pastTournaments as $t)
            @php $tTitle = $niceTitle($t->translation($locale)?->title ?? $t->slug); @endphp
            <li class="border-b border-dark-border">
                <a href="{{ lroute('tournament.show', ['slug' => $t->slug]) }}" class="group flex items-center gap-4 py-4">
                    @if($t->cover_image)
                        <img src="{{ Storage::url($t->cover_image) }}" alt="" loading="lazy" width="96" height="72"
                             class="w-24 h-[72px] object-cover flex-shrink-0">
                    @else
                        <span class="w-24 h-[72px] flex-shrink-0 bg-dark-card border border-dark-border"></span>
                    @endif
                    <span class="min-w-0">
                        <span class="block font-bold text-white group-hover:text-gold transition-colors leading-snug">{{ $tTitle }}</span>
                        <span class="block text-sm text-gray-400 mt-1">{{ $dateRange($t->date_start, $t->date_end) }}@if($t->location) · {{ $t->location }}@endif</span>
                    </span>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- ===================== SPONSORS (full colour, once) ===================== --}}
@if($globalSponsors->count() > 0)
@php
    $tiers = $globalSponsors->sortBy(fn($s) => ['gold' => 0, 'silver' => 1, 'bronze' => 2][$s->category] ?? 3)->groupBy(fn($s) => $s->category === 'gold' ? 'top' : 'rest');
@endphp
<section class="bg-dark-card border-t border-dark-border">
    <div class="max-w-6xl mx-auto px-4 py-14 md:py-20">
        <h2 class="text-2xl md:text-3xl font-black text-white mb-2">{{ __('messages.home_sponsors') }}</h2>
        <p class="text-gray-400 mb-8">{{ __('messages.sponsors_section_subtitle') }}</p>
        @foreach(['top' => 'grid-cols-2 md:grid-cols-4 mb-3', 'rest' => 'grid-cols-3 md:grid-cols-6'] as $tier => $cols)
            @if(isset($tiers[$tier]))
            <ul class="grid {{ $cols }} gap-3">
                @foreach($tiers[$tier] as $sponsor)
                <li>
                    @php $tag = $sponsor->url ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if($sponsor->url) href="{{ $sponsor->url }}" target="_blank" rel="noopener" @endif
                        class="flex items-center justify-center {{ $tier === 'top' ? 'h-24 md:h-28 p-5' : 'h-16 md:h-20 p-3' }} bg-dark border border-dark-border {{ $sponsor->url ? 'hover:border-gold/60 transition-colors' : '' }}">
                        <img src="{{ Storage::url($sponsor->logo) }}" alt="{{ $sponsor->name }}" loading="lazy"
                             class="max-h-full max-w-full w-auto object-contain">
                    </{{ $tag }}>
                </li>
                @endforeach
            </ul>
            @endif
        @endforeach
    </div>
</section>
@endif

@if($next && !$isLive)
@push('scripts')
<script>
// Countdown: the server already rendered the right numbers; this only keeps
// them ticking once a minute (no seconds, so nothing on the page flickers).
(function () {
    var box = document.querySelector('[data-countdown]');
    if (!box) return;
    var target = new Date(box.dataset.countdown).getTime();
    var cells = { d: box.querySelector('[data-unit="d"]'), h: box.querySelector('[data-unit="h"]'), m: box.querySelector('[data-unit="m"]') };
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
        var mins = Math.max(0, Math.floor((target - Date.now()) / 60000));
        cells.d.textContent = pad(Math.floor(mins / 1440));
        cells.h.textContent = pad(Math.floor(mins % 1440 / 60));
        cells.m.textContent = pad(mins % 60);
    }
    tick();
    setInterval(tick, 30000);
})();
</script>
@endpush
@endif

@endsection
