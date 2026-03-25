@extends('layouts.app')

@php
    $locale         = app()->getLocale();
    $tournamentName = $tournament
        ? ($tournament->translation($locale)?->title ?? $tournament->slug)
        : 'Padelio Čempionatas ' . now()->year;

    $deadline     = $s('proposal_deadline');
    $urgency      = $st('proposal_urgency_text');
    $headline     = $st('proposal_headline', __('messages.hero_tagline'));
    $subheadline  = $st('proposal_subheadline');
    $valueAnchor  = $st('proposal_value_anchor');
    $audience     = array_filter(explode("\n", $st('proposal_audience')));
    $caseStudy    = $st('proposal_case_study');
    $contactName  = $s('proposal_contact_name');
    $contactEmail = $s('proposal_contact_email');
    $contactPhone = $s('proposal_contact_phone');

    // Streaming section
    $streamTitle   = $st('proposal_stream_title', __('messages.proposal_stream_label'));
    $streamText    = $st('proposal_stream_text');
    $streamUrl     = $s('proposal_stream_url');
    $streamUrlLabel= $st('proposal_stream_url_label', __('messages.proposal_stream_label'));
    $hasStream     = $streamText || count($streamStats) > 0 || $streamPhoto || $streamUrl;

    // ROI stats with translated labels
    $roiStats = array_filter([
        ['icon' => '🏃', 'value' => $s('stat_participants'),  'label' => __('messages.proposal_stat_participants')],
        ['icon' => '👁',  'value' => $s('stat_viewers'),      'label' => __('messages.proposal_stat_viewers')],
        ['icon' => '📱',  'value' => $s('stat_social_reach'), 'label' => __('messages.proposal_stat_social')],
        ['icon' => '🎥',  'value' => $s('stat_video_views'),  'label' => __('messages.proposal_stat_video')],
        ['icon' => '🤝',  'value' => $s('stat_partners'),     'label' => __('messages.proposal_stat_partners')],
        ['icon' => '📰',  'value' => $s('stat_media'),        'label' => __('messages.proposal_stat_media')],
    ], fn ($r) => ! empty($r['value']));
@endphp

@section('title', __('messages.proposal_become_sponsor') . ' · ' . $tournamentName)

@section('og_type', 'website')
@section('og_title', __('messages.proposal_become_sponsor') . ' — ' . $tournamentName)
@section('og_description', Str::limit(strip_tags($subheadline ?: 'Tapkite oficialiu padelio turnyro rėmėju. Pasiekite aktyvią sporto auditoriją ir padidinkite savo prekės ženklo matomumą.'), 160))
@if(count($photos) > 0)
    @section('og_image', $photos[0])
@endif

@section('content')

{{-- ═══ HERO ═══════════════════════════════════════════════════════════════ --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    @if(count($photos) > 0)
        <img src="{{ Storage::url($photos[0]) }}" alt=""
             class="absolute inset-0 w-full h-full object-cover scale-105"
             style="filter:brightness(0.22) saturate(0.5);">
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-[#0A0A0F] via-[#0d1117] to-[#111118]"></div>
    @endif
    <div class="absolute inset-0"
         style="background:linear-gradient(135deg,rgba(201,168,76,.06) 0%,transparent 60%)"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-6 py-32 text-center">
        @if($urgency)
            <div class="inline-flex items-center gap-2 bg-red-500/20 border border-red-500/40 text-red-400 text-xs font-bold uppercase tracking-widest px-4 py-2 mb-8 animate-pulse">
                <span class="w-2 h-2 bg-red-500 rounded-full"></span>{{ $urgency }}
            </div>
        @endif

        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-4">
            {{ __('messages.proposal_partnership_label') }} · {{ now()->year }}
        </p>

        <h1 class="text-5xl md:text-7xl font-black text-white leading-tight mb-6">
            {{ __('messages.proposal_become_sponsor') }}<br>
            <span class="text-gold">{{ $tournamentName }}</span>
        </h1>

        <p class="text-lg md:text-xl text-gray-300 mb-4 font-medium">{{ $headline }}</p>
        <p class="text-gold/80 text-sm md:text-base font-semibold tracking-wider mb-12">{{ $subheadline }}</p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="#paketai"
               class="inline-block bg-gold text-dark font-black text-base px-10 py-4 uppercase tracking-wider hover:bg-gold-light transition-colors">
                {{ __('messages.proposal_view_packages') }}
            </a>
            @if($contactEmail)
                <a href="mailto:{{ $contactEmail }}"
                   class="inline-block border border-white/20 text-gray-300 font-semibold text-base px-10 py-4 hover:border-gold hover:text-gold transition-colors">
                    {{ __('messages.proposal_contact_direct') }}
                </a>
            @endif
        </div>

        @if($deadline)
            <p class="mt-8 text-gray-500 text-sm">
                {{ __('messages.proposal_deadline_label') }}
                <span class="text-gold font-semibold">{{ $deadline }}</span>
            </p>
        @endif
    </div>

    <div class="absolute bottom-10 left-1/2 -translate-x-1/2 flex flex-col items-center gap-1 opacity-40">
        <svg class="w-5 h-5 text-gold animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        <svg class="w-5 h-5 text-gold animate-bounce" style="animation-delay:.15s" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </div>
</section>

{{-- ═══ ROI / PREVIOUS YEAR RESULTS ════════════════════════════════════════ --}}
@if(count($roiStats) > 0)
<section class="py-24 bg-dark-card" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6">
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">{{ __('messages.proposal_roi_label') }}</p>
        <h2 class="text-4xl md:text-5xl font-black text-white mb-4">{{ __('messages.proposal_roi_title') }}</h2>
        <p class="text-gray-400 mb-12 max-w-xl">{{ __('messages.proposal_roi_subtitle') }}</p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach($roiStats as $stat)
                <div class="bg-dark border border-dark-border p-6 group hover:border-gold transition-colors"
                     data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                    <div class="text-3xl mb-3">{{ $stat['icon'] }}</div>
                    <div class="text-4xl font-black text-white group-hover:text-gold transition-colors mb-1">{{ $stat['value'] }}</div>
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
                <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">{{ __('messages.proposal_audience_label') }}</p>
                <h2 class="text-4xl md:text-5xl font-black text-white mb-8 leading-tight">
                    {{ __('messages.proposal_audience_title') }}<br>
                    <span class="text-gold">{{ __('messages.proposal_audience_title_gold') }}</span>
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
            @if(count($photos) > 1)
                <div class="relative aspect-[4/3] overflow-hidden">
                    <img src="{{ Storage::url($photos[1]) }}" alt="" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-tr from-dark/60 to-transparent"></div>
                </div>
            @else
                <div class="aspect-[4/3] bg-dark-card border border-dark-border flex items-center justify-center">
                    <p class="text-gray-600 text-sm">Pridėkite nuotrauką admin puslapyje</p>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- ═══ CASE STUDY ══════════════════════════════════════════════════════════ --}}
@if($caseStudy)
<section class="py-24 bg-dark-card" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6">
        <div class="grid md:grid-cols-2 gap-16 items-center">
            @if(count($photos) > 2)
                <div class="relative aspect-[4/3] overflow-hidden order-2 md:order-1">
                    <img src="{{ Storage::url($photos[2]) }}" alt="" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-tl from-dark/60 to-transparent"></div>
                </div>
            @endif
            <div class="{{ count($photos) > 2 ? 'order-1 md:order-2' : 'md:col-span-2 max-w-2xl' }}">
                <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">{{ __('messages.proposal_casestudy_label') }}</p>
                <h2 class="text-4xl font-black text-white mb-6">{{ __('messages.proposal_casestudy_title') }}</h2>
                <div class="text-gray-300 text-lg leading-relaxed whitespace-pre-line">{{ $caseStudy }}</div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ═══ LIVE STREAMING ══════════════════════════════════════════════════════ --}}
@if($hasStream)
<section class="py-24" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6">
        <div class="grid {{ ($streamPhoto) ? 'md:grid-cols-2' : 'grid-cols-1' }} gap-16 items-center">

            {{-- Text + stats side --}}
            <div>
                <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">
                    {{ __('messages.proposal_stream_label') }}
                </p>
                <h2 class="text-4xl font-black text-white mb-6 leading-tight">{{ $streamTitle }}</h2>

                @if($streamText)
                    <p class="text-gray-300 text-lg leading-relaxed mb-8 whitespace-pre-line">{{ $streamText }}</p>
                @endif

                {{-- Streaming stats grid --}}
                @if(count($streamStats) > 0)
                    <div class="grid grid-cols-2 gap-4 mb-8">
                        @foreach($streamStats as $stat)
                            <div class="bg-dark-card border border-dark-border p-5 group hover:border-gold transition-colors">
                                <div class="text-3xl font-black text-white group-hover:text-gold transition-colors mb-1">
                                    {{ $stat['value'] }}
                                </div>
                                <div class="text-gray-500 text-xs uppercase tracking-wider">{{ $stat['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- External link button --}}
                @if($streamUrl)
                    <a href="{{ $streamUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 border border-gold text-gold px-7 py-3 font-bold text-sm hover:bg-gold hover:text-dark transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $streamUrlLabel ?: __('messages.proposal_stream_label') }}
                    </a>
                @endif
            </div>

            {{-- Photo side --}}
            @if($streamPhoto)
                <div class="relative aspect-[4/3] overflow-hidden">
                    <img src="{{ Storage::url($streamPhoto) }}" alt="{{ $streamTitle }}"
                         class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-tl from-dark/50 to-transparent"></div>
                    {{-- Play overlay hint --}}
                    @if($streamUrl)
                        <a href="{{ $streamUrl }}" target="_blank" rel="noopener"
                           class="absolute inset-0 flex items-center justify-center group">
                            <div class="w-16 h-16 bg-gold/90 group-hover:bg-gold rounded-full flex items-center justify-center transition-colors shadow-lg shadow-gold/30">
                                <svg class="w-7 h-7 text-dark ml-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- ═══ CURRENT SPONSORS ═══════════════════════════════════════════════════ --}}
@if($currentSponsors->count() > 0)
<section class="py-20" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6 text-center">
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">
            {{ __('messages.proposal_current_sponsors_label') }}
        </p>
        <h2 class="text-3xl md:text-4xl font-black text-white mb-3">
            {{ __('messages.proposal_current_sponsors_title') }}
        </h2>
        <p class="text-gray-500 text-sm mb-12 max-w-lg mx-auto">
            {{ __('messages.proposal_current_sponsors_sub') }}
        </p>

        @php
            $grouped = $currentSponsors->sortBy('sort_order')->groupBy('category');
            $order   = ['gold', 'silver', 'bronze', 'general'];
        @endphp

        @foreach($order as $cat)
            @if($grouped->has($cat))
                <div class="{{ !$loop->first ? 'mt-8' : '' }}">
                    <div class="flex flex-wrap justify-center items-center gap-5">
                        @foreach($grouped[$cat] as $sponsor)
                            <div class="group flex items-center justify-center
                                        {{ $cat === 'gold'   ? 'w-44 h-20' :
                                           ($cat === 'silver' ? 'w-36 h-16' : 'w-28 h-12') }}
                                        bg-dark-card border border-dark-border hover:border-gold/40 transition-colors px-4">
                                @if($sponsor->logo)
                                    <img src="{{ Storage::url($sponsor->logo) }}"
                                         alt="{{ $sponsor->name }}"
                                         class="max-w-full max-h-full object-contain"
                                         style="filter:grayscale(1) brightness(1.8); transition:filter .3s"
                                         onmouseover="this.style.filter='none'"
                                         onmouseout="this.style.filter='grayscale(1) brightness(1.8)'">
                                @else
                                    <span class="text-gray-400 text-xs font-semibold text-center leading-tight">{{ $sponsor->name }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>
@endif

{{-- ═══ PACKAGES ═══════════════════════════════════════════════════════════ --}}
@if($tiers->count() > 0)
<section id="paketai" class="py-24 bg-dark-card" data-aos="fade-up">
    <div class="max-w-5xl mx-auto px-6">
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-2">{{ __('messages.proposal_packages_label') }}</p>
        <h2 class="text-4xl md:text-5xl font-black text-white mb-4">{{ __('messages.proposal_packages_title') }}</h2>

        @if($valueAnchor)
            <div class="border-l-4 border-gold bg-gold/5 p-5 mb-12 max-w-2xl">
                <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">{{ __('messages.proposal_anchor_label') }}</p>
                <p class="text-white font-black text-xl mb-1">{{ $valueAnchor }}</p>
                <p class="text-gold text-sm font-semibold">{{ __('messages.proposal_anchor_note') }}</p>
            </div>
        @endif

        <div class="grid @if($tiers->count() === 1) grid-cols-1 max-w-sm mx-auto
                     @elseif($tiers->count() === 2) grid-cols-1 md:grid-cols-2
                     @else grid-cols-1 md:grid-cols-2 lg:grid-cols-3
                     @endif gap-6">

            @foreach($tiers as $tier)
                @php $slotsLeft = $tier->slots_total ? max(0, $tier->slots_total - $tier->slots_taken) : null; @endphp
                <div class="relative flex flex-col border-2 transition-colors
                            {{ $tier->highlighted
                                ? 'border-gold bg-gradient-to-b from-gold/8 to-dark shadow-[0_0_40px_rgba(201,168,76,0.15)]'
                                : 'border-dark-border bg-dark hover:border-gold/30' }}"
                     data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">

                    @if($tier->highlighted)
                        <div class="absolute -top-px left-1/2 -translate-x-1/2">
                            <span class="bg-gold text-dark text-xs font-black uppercase tracking-widest px-4 py-1">
                                {{ __('messages.proposal_recommended') }}
                            </span>
                        </div>
                    @endif

                    <div class="p-8 flex flex-col h-full {{ $tier->highlighted ? 'pt-10' : '' }}">

                        @if($slotsLeft !== null)
                            @php $pct = $tier->slots_total > 0 ? min(100, round(($tier->slots_taken / $tier->slots_total) * 100)) : 0; @endphp
                            <div class="mb-6">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-500">{{ __('messages.proposal_slots_label') }}</span>
                                    <span class="{{ $slotsLeft <= 1 ? 'text-red-400' : 'text-gold' }} font-bold">
                                        {{ $slotsLeft > 0
                                            ? __('messages.proposal_slots_left', ['n' => $slotsLeft])
                                            : __('messages.proposal_slots_full') }}
                                    </span>
                                </div>
                                <div class="h-1.5 bg-dark-border rounded-full overflow-hidden">
                                    <div class="h-full {{ $pct >= 80 ? 'bg-red-500' : 'bg-gold' }}"
                                         style="width:{{ $pct }}%"></div>
                                </div>
                            </div>
                        @endif

                        <h3 class="text-2xl font-black text-white mb-1">{{ $tier->localeName() }}</h3>
                        @php $tierTagline = $tier->localeTagline(); @endphp
                        @if($tierTagline)
                            <p class="text-gray-500 text-sm mb-6">{{ $tierTagline }}</p>
                        @else
                            <div class="mb-6"></div>
                        @endif

                        <div class="mb-8">
                            <span class="{{ $tier->highlighted ? 'text-gold' : 'text-white' }} text-5xl font-black">
                                {{ number_format($tier->price, 0, '.', ' ') }} €
                            </span>
                            @if($tier->price_suffix)
                                <span class="text-gray-400 text-sm ml-1">{{ $tier->price_suffix }}</span>
                            @endif
                        </div>

                        @php $tierBenefits = $tier->localeBenefits(); @endphp
                        @if($tierBenefits)
                            <ul class="space-y-3 flex-1 mb-8">
                                @foreach($tierBenefits as $benefit)
                                    <li class="flex items-start gap-3 text-gray-300 text-sm">
                                        <svg class="w-4 h-4 text-gold flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ is_array($benefit) ? ($benefit['benefit'] ?? '') : $benefit }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if($contactEmail || $contactPhone)
                            <a href="{{ $contactEmail ? 'mailto:'.$contactEmail.'?subject='.urlencode('Rėmimas: '.$tier->localeName().' paketas') : '#kontaktai' }}"
                               class="block text-center py-3.5 font-black text-sm uppercase tracking-wider transition-colors
                                      {{ $tier->highlighted ? 'bg-gold text-dark hover:bg-gold-light' : 'border border-dark-border text-gray-300 hover:border-gold hover:text-gold' }}
                                      {{ $slotsLeft === 0 ? 'opacity-40 pointer-events-none' : '' }}">
                                {{ $slotsLeft === 0 ? __('messages.proposal_no_slots') : __('messages.proposal_reserve') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-center text-gray-600 text-sm mt-8">{{ __('messages.proposal_packages_note') }}</p>
    </div>
</section>
@endif

{{-- ═══ PHOTO GALLERY ══════════════════════════════════════════════════════ --}}
@if(count($photos) > 3)
<section class="py-6 bg-dark overflow-hidden" data-aos="fade-up">
    <div class="max-w-6xl mx-auto px-6 mb-6">
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em]">{{ __('messages.proposal_atmosphere') }}</p>
    </div>
    <div class="flex gap-3 px-6 overflow-x-auto pb-4 max-w-6xl mx-auto" style="scrollbar-width:thin">
        @foreach(array_slice($photos, 1) as $photo)
            <a href="{{ Storage::url($photo) }}"
               class="glightbox flex-shrink-0 w-56 h-40 overflow-hidden relative group"
               data-gallery="proposal-gallery">
                <img src="{{ Storage::url($photo) }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <div class="absolute inset-0 bg-dark/0 group-hover:bg-dark/20 transition-colors"></div>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ═══ CTA / CONTACT ══════════════════════════════════════════════════════ --}}
<section id="kontaktai" class="py-32 relative overflow-hidden" data-aos="fade-up">
    <div class="absolute inset-0 bg-gradient-to-b from-dark-card to-dark"></div>
    <div class="absolute inset-0" style="background:radial-gradient(ellipse at center,rgba(201,168,76,.06) 0%,transparent 70%)"></div>

    <div class="relative z-10 max-w-3xl mx-auto px-6 text-center">
        <p class="text-gold text-xs font-bold uppercase tracking-[0.4em] mb-4">{{ __('messages.proposal_cta_label') }}</p>

        <h2 class="text-4xl md:text-6xl font-black text-white mb-6 leading-tight">
            {{ __('messages.proposal_cta_title') }}<br>
            <span class="text-gold">{{ __('messages.proposal_cta_title_gold') }}</span>
        </h2>

        @if($urgency)
            <div class="inline-flex items-center gap-2 bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-semibold px-5 py-2.5 mb-6">
                <span class="w-2 h-2 bg-red-400 rounded-full animate-pulse"></span>
                {{ $urgency }}
            </div>
        @endif

        @if($deadline)
            <p class="text-gray-400 mb-10">
                {!! __('messages.proposal_cta_deadline', ['date' => '<span class="text-white font-bold">'.$deadline.'</span>']) !!}
            </p>
        @endif

        @if($contactName || $contactEmail || $contactPhone)
            <div class="bg-dark-card border border-dark-border p-8 text-left max-w-md mx-auto mb-10">
                <p class="text-gold text-xs uppercase tracking-widest mb-4 font-bold">{{ __('messages.proposal_contact_info') }}</p>
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
                    <a href="tel:{{ preg_replace('/\s+/','', $contactPhone) }}"
                       class="flex items-center gap-3 text-gray-300 hover:text-gold transition-colors">
                        <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ $contactPhone }}
                    </a>
                @endif
            </div>
        @endif

        <a href="{{ $contactEmail ? 'mailto:'.$contactEmail.'?subject='.urlencode('Rėmimo pasiūlymas') : lroute('contact') }}"
           class="inline-block bg-gold text-dark font-black text-lg px-12 py-5 uppercase tracking-wider hover:bg-gold-light transition-colors">
            {{ __('messages.proposal_cta_btn') }}
        </a>
    </div>
</section>

@endsection

@push('scripts')
<script>
    GLightbox({ selector: '.glightbox' });
</script>
@endpush
