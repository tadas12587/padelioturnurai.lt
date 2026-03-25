@extends('layouts.app')

@php
    $tournamentName = $tournament
        ? ($tournament->translation(app()->getLocale())?->title ?? $tournament->slug)
        : 'Padelio Čempionatas 2025';

    $deadline      = $s('proposal_deadline');
    $urgency       = $s('proposal_urgency_text');
    $headline      = $s('proposal_headline', 'Didžiausias padelio turnyras Lietuvoje');
    $subheadline   = $s('proposal_subheadline', '300+ žaidėjų · 10 000+ žiūrovų · TOP vasaros sporto renginys');
    $valueAnchor   = $s('proposal_value_anchor');
    $audience      = array_filter(explode("\n", $s('proposal_audience')));
    $caseStudy     = $s('proposal_case_study');
    $contactName   = $s('proposal_contact_name');
    $contactEmail  = $s('proposal_contact_email');
    $contactPhone  = $s('proposal_contact_phone');

    $stats = array_filter([
        ['icon' => '🏃', 'value' => $s('stat_participants'),  'label' => 'Dalyvių'],
        ['icon' => '👁',  'value' => $s('stat_viewers'),      'label' => 'Žiūrovų'],
        ['icon' => '📱',  'value' => $s('stat_social_reach'), 'label' => 'Social media reach'],
        ['icon' => '🎥',  'value' => $s('stat_video_views'),  'label' => 'Video peržiūros'],
        ['icon' => '🤝',  'value' => $s('stat_partners'),     'label' => 'Partnerių'],
        ['icon' => '📰',  'value' => $s('stat_media'),        'label' => 'Media publikacijos'],
    ], fn ($s) => ! empty($s['value']));
@endphp

@section('title', 'Tapk rėmėju · ' . $tournamentName)

@section('content')

{{-- ═══ HERO ═══════════════════════════════════════════════════════════════ --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">

    {{-- Background photo from proposal photos (first one) --}}
    @if(count($photos) > 0)
        <img src="{{ Storage::url($photos[0]) }}"
             alt="" class="absolute inset-0 w-full h-full object-cover scale-105"
             style="filter:brightness(0.25) saturate(0.6);">
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-[#0A0A0F] via-[#0d1117] to-[#111118]"></div>
    @endif

    {{-- Gold diagonal accent --}}
    <div class="absolute inset-0"
         style="background: linear-gradient(135deg, rgba(201,168,76,0.06) 0%, transparent 60%);"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-6 py-32 text-center">

        {{-- Urgency badge --}}
        @if($urgency)
            <div class="inline-flex items-center gap-2 bg-red-500/20 border border-red-500/40 text-red-400 text-xs font-bold uppercase tracking-widest px-4 py-2 mb-8 animate-pulse">
                <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                {{ $urgency }}
            </div>
        @endif

        {{-- Label --}}
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-4">
            Partnerystės pasiūlymas · {{ now()->year }}
        </p>

        {{-- Title --}}
        <h1 class="text-5xl md:text-7xl font-black text-white leading-tight mb-6">
            Tapkite<br>
            <span class="text-gold">{{ $tournamentName }}</span><br>
            rėmėju
        </h1>

        {{-- Headline --}}
        <p class="text-lg md:text-xl text-gray-300 mb-4 font-medium">{{ $headline }}</p>

        {{-- Subheadline stats strip --}}
        <p class="text-gold/80 text-sm md:text-base font-semibold tracking-wider mb-12">
            {{ $subheadline }}
        </p>

        {{-- CTAs --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="#paketai"
               class="inline-block bg-gold text-dark font-black text-base px-10 py-4 uppercase tracking-wider hover:bg-gold-light transition-colors">
                Peržiūrėti paketus ↓
            </a>
            @if($contactEmail)
                <a href="mailto:{{ $contactEmail }}"
                   class="inline-block border border-white/20 text-gray-300 font-semibold text-base px-10 py-4 hover:border-gold hover:text-gold transition-colors">
                    Susisiekti tiesiogiai
                </a>
            @endif
        </div>

        @if($deadline)
            <p class="mt-8 text-gray-500 text-sm">
                Registracija rėmėjams iki: <span class="text-gold font-semibold">{{ $deadline }}</span>
            </p>
        @endif
    </div>

    {{-- Scroll indicator --}}
    <div class="absolute bottom-10 left-1/2 -translate-x-1/2 flex flex-col items-center gap-1 opacity-40">
        <svg class="w-5 h-5 text-gold animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
        <svg class="w-5 h-5 text-gold animate-bounce" style="animation-delay:.15s" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
</section>

{{-- ═══ PREVIOUS YEAR RESULTS (ROI) ═══════════════════════════════════════ --}}
@if(count($stats) > 0)
<section class="py-24 bg-dark-card" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6">
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">
            Praeitų metų rezultatai
        </p>
        <h2 class="text-4xl md:text-5xl font-black text-white mb-4">
            Skaičiai kalba patys
        </h2>
        <p class="text-gray-400 mb-12 max-w-xl">
            Tai yra jūsų reklaminė investicija — pasiekiamumas, kurį gausite tapę mūsų partneriu.
        </p>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach($stats as $stat)
                <div class="bg-dark border border-dark-border p-6 group hover:border-gold transition-colors"
                     data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                    <div class="text-3xl mb-3">{{ $stat['icon'] }}</div>
                    <div class="text-4xl font-black text-white group-hover:text-gold transition-colors mb-1">
                        {{ $stat['value'] }}
                    </div>
                    <div class="text-gray-500 text-sm uppercase tracking-wider">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ═══ TARGET AUDIENCE ════════════════════════════════════════════════════ --}}
@if(count($audience) > 0)
<section class="py-24" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6">
        <div class="grid md:grid-cols-2 gap-16 items-center">
            <div>
                <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">Jūsų rinka</p>
                <h2 class="text-4xl md:text-5xl font-black text-white mb-8 leading-tight">
                    Pasiekite<br>savo tikslinę<br>
                    <span class="text-gold">auditoriją</span>
                </h2>
                <ul class="space-y-4">
                    @foreach($audience as $line)
                        @php $line = trim($line); @endphp
                        @if($line)
                            <li class="flex items-start gap-4">
                                <span class="flex-shrink-0 w-6 h-6 bg-gold/10 border border-gold/30 text-gold text-xs flex items-center justify-center mt-0.5 font-bold">✓</span>
                                <span class="text-gray-200 font-medium">{{ ltrim($line, '✔✓-• ') }}</span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>

            {{-- Photo --}}
            @if(count($photos) > 1)
                <div class="relative aspect-[4/3] overflow-hidden">
                    <img src="{{ Storage::url($photos[1]) }}" alt="Padelio turnyras"
                         class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-tr from-dark/60 to-transparent"></div>
                </div>
            @else
                <div class="aspect-[4/3] bg-dark-card border border-dark-border flex items-center justify-center">
                    <div class="text-center text-gray-600">
                        <svg class="w-16 h-16 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm">Pridėkite nuotrauka admin puslapyje</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- ═══ CASE STUDY / PREVIOUS PARTNERS ════════════════════════════════════ --}}
@if($caseStudy)
<section class="py-24 bg-dark-card" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6">
        <div class="grid md:grid-cols-2 gap-16 items-center">
            @if(count($photos) > 2)
                <div class="relative aspect-[4/3] overflow-hidden order-2 md:order-1">
                    <img src="{{ Storage::url($photos[2]) }}" alt="Praeitų metų turnyras"
                         class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-tl from-dark/60 to-transparent"></div>
                </div>
            @endif
            <div class="{{ count($photos) > 2 ? 'order-1 md:order-2' : 'md:col-span-2 max-w-2xl' }}">
                <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">
                    Sėkmės istorija
                </p>
                <h2 class="text-4xl font-black text-white mb-6">
                    Jie jau pasitikėjo mumis
                </h2>
                <div class="text-gray-300 text-lg leading-relaxed whitespace-pre-line">{{ $caseStudy }}</div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ═══ PACKAGES ═══════════════════════════════════════════════════════════ --}}
@if($tiers->count() > 0)
<section id="paketai" class="py-24" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6">

        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">Partnerystės paketai</p>
        <h2 class="text-4xl md:text-5xl font-black text-white mb-4">Pasirinkite savo paketą</h2>

        {{-- Value anchor --}}
        @if($valueAnchor)
            <div class="border-l-4 border-gold bg-gold/5 p-5 mb-12 max-w-2xl">
                <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Palyginimui</p>
                <p class="text-white font-black text-xl mb-1">{{ $valueAnchor }}</p>
                <p class="text-gold text-sm font-semibold">
                    Mūsų paketai suteikia tą patį matomumą už dalį šios kainos →
                </p>
            </div>
        @endif

        {{-- Tiers grid --}}
        <div class="grid @if($tiers->count() === 1) grid-cols-1 max-w-sm mx-auto
                     @elseif($tiers->count() === 2) grid-cols-1 md:grid-cols-2
                     @else grid-cols-1 md:grid-cols-2 lg:grid-cols-3
                     @endif gap-6">

            @foreach($tiers as $tier)
                @php
                    $slotsLeft = $tier->slots_total ? max(0, $tier->slots_total - $tier->slots_taken) : null;
                @endphp
                <div class="relative flex flex-col border-2 transition-colors
                            {{ $tier->highlighted
                                ? 'border-gold bg-gradient-to-b from-gold/8 to-dark-card shadow-[0_0_40px_rgba(201,168,76,0.15)]'
                                : 'border-dark-border bg-dark-card hover:border-gold/30' }}"
                     data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">

                    {{-- Recommended badge --}}
                    @if($tier->highlighted)
                        <div class="absolute -top-px left-1/2 -translate-x-1/2">
                            <span class="bg-gold text-dark text-xs font-black uppercase tracking-widest px-4 py-1">
                                Rekomenduojama
                            </span>
                        </div>
                    @endif

                    <div class="p-8 flex flex-col h-full {{ $tier->highlighted ? 'pt-10' : '' }}">

                        {{-- Slots availability bar --}}
                        @if($slotsLeft !== null)
                            @php $pct = $tier->slots_total > 0 ? min(100, round(($tier->slots_taken / $tier->slots_total) * 100)) : 0; @endphp
                            <div class="mb-6">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-500">Vietų užimtumas</span>
                                    <span class="{{ $slotsLeft <= 1 ? 'text-red-400' : 'text-gold' }} font-bold">
                                        {{ $slotsLeft > 0 ? "Liko {$slotsLeft}" : 'Užimta' }}
                                    </span>
                                </div>
                                <div class="h-1.5 bg-dark-border rounded-full overflow-hidden">
                                    <div class="h-full {{ $pct >= 80 ? 'bg-red-500' : 'bg-gold' }} transition-all"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endif

                        {{-- Name & tagline --}}
                        <h3 class="text-2xl font-black text-white mb-1">{{ $tier->name }}</h3>
                        @if($tier->tagline)
                            <p class="text-gray-500 text-sm mb-6">{{ $tier->tagline }}</p>
                        @else
                            <div class="mb-6"></div>
                        @endif

                        {{-- Price --}}
                        <div class="mb-8">
                            <span class="{{ $tier->highlighted ? 'text-gold' : 'text-white' }} text-5xl font-black">
                                {{ number_format($tier->price, 0, '.', ' ') }} €
                            </span>
                            @if($tier->price_suffix)
                                <span class="text-gray-400 text-sm ml-1">{{ $tier->price_suffix }}</span>
                            @endif
                        </div>

                        {{-- Benefits --}}
                        @if($tier->benefits)
                            <ul class="space-y-3 flex-1 mb-8">
                                @foreach($tier->benefits as $benefit)
                                    <li class="flex items-start gap-3 text-gray-300 text-sm">
                                        <svg class="w-4 h-4 text-gold flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ is_array($benefit) ? ($benefit['benefit'] ?? '') : $benefit }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        {{-- CTA --}}
                        @if($contactEmail || $contactPhone)
                            <a href="{{ $contactEmail ? 'mailto:'.$contactEmail.'?subject=Rėmimas: '.$tier->name.' paketas' : '#kontaktai' }}"
                               class="block text-center py-3.5 font-black text-sm uppercase tracking-wider transition-colors
                                      {{ $tier->highlighted
                                          ? 'bg-gold text-dark hover:bg-gold-light'
                                          : 'border border-dark-border text-gray-300 hover:border-gold hover:text-gold' }}
                                      {{ $slotsLeft === 0 ? 'opacity-40 pointer-events-none' : '' }}">
                                {{ $slotsLeft === 0 ? 'Vietų nėra' : 'Rezervuoti vietą' }}
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Packages comparison note --}}
        <p class="text-center text-gray-600 text-sm mt-8">
            Visi paketai apima papildomus susitarimus pagal poreikį. Susisiekite dėl individualaus pasiūlymo.
        </p>
    </div>
</section>
@endif

{{-- ═══ PHOTO GALLERY (proposal photos) ══════════════════════════════════ --}}
@if(count($photos) > 3)
<section class="py-6 bg-dark-card overflow-hidden" data-aos="fade-up">
    <div class="max-w-6xl mx-auto px-6 mb-6">
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em]">Turnyro atmosfera</p>
    </div>
    <div class="flex gap-3 px-6 overflow-x-auto pb-4 max-w-6xl mx-auto" style="scrollbar-width:thin">
        @foreach(array_slice($photos, 1) as $photo)
            <a href="{{ Storage::url($photo) }}"
               class="glightbox flex-shrink-0 w-56 h-40 overflow-hidden relative group"
               data-gallery="proposal-gallery">
                <img src="{{ Storage::url($photo) }}" alt=""
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <div class="absolute inset-0 bg-dark/0 group-hover:bg-dark/20 transition-colors"></div>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ═══ CTA / CONTACT ══════════════════════════════════════════════════════ --}}
<section id="kontaktai" class="py-32 relative overflow-hidden" data-aos="fade-up">
    <div class="absolute inset-0 bg-gradient-to-b from-dark-card to-dark"></div>
    <div class="absolute inset-0"
         style="background: radial-gradient(ellipse at center, rgba(201,168,76,0.06) 0%, transparent 70%);"></div>

    <div class="relative z-10 max-w-3xl mx-auto px-6 text-center">
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-4">Tapkite partneriu</p>

        <h2 class="text-4xl md:text-6xl font-black text-white mb-6 leading-tight">
            Tapkite renginio dalimi,<br>
            apie kurį <span class="text-gold">kalbės visa Lietuva</span>
        </h2>

        @if($urgency)
            <div class="inline-flex items-center gap-2 bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-semibold px-5 py-2.5 mb-6">
                <span class="w-2 h-2 bg-red-400 rounded-full animate-pulse"></span>
                {{ $urgency }}
            </div>
        @endif

        @if($deadline)
            <p class="text-gray-400 mb-10">
                Rezervuokite partnerystę iki
                <span class="text-white font-bold">{{ $deadline }}</span>
                — vietų skaičius ribotas.
            </p>
        @endif

        {{-- Contact info --}}
        @if($contactName || $contactEmail || $contactPhone)
            <div class="bg-dark-card border border-dark-border p-8 text-left max-w-md mx-auto mb-10">
                <p class="text-gold text-xs uppercase tracking-widest mb-4 font-bold">Kontaktinė informacija</p>
                @if($contactName)
                    <p class="text-white font-semibold text-lg mb-3">{{ $contactName }}</p>
                @endif
                @if($contactEmail)
                    <a href="mailto:{{ $contactEmail }}"
                       class="flex items-center gap-3 text-gray-300 hover:text-gold transition-colors mb-2">
                        <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        {{ $contactEmail }}
                    </a>
                @endif
                @if($contactPhone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}"
                       class="flex items-center gap-3 text-gray-300 hover:text-gold transition-colors">
                        <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ $contactPhone }}
                    </a>
                @endif
            </div>
        @endif

        <a href="{{ $contactEmail ? 'mailto:'.$contactEmail.'?subject=Rėmimo pasiūlymas' : lroute('contact') }}"
           class="inline-block bg-gold text-dark font-black text-lg px-12 py-5 uppercase tracking-wider hover:bg-gold-light transition-colors">
            Susisiekti dabar →
        </a>
    </div>
</section>

@endsection

@push('scripts')
<script>
    GLightbox({ selector: '.glightbox' });
</script>
@endpush
