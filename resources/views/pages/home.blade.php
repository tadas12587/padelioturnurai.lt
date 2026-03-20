@extends('layouts.app')

@section('title', 'Padelio Turnyrai - ' . __('messages.nav_home'))

@section('content')

{{-- Hero Section --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    {{-- Background gradient --}}
    <div class="absolute inset-0 bg-gradient-to-br from-dark via-[#0d1117] to-[#1a1a2e]"></div>

    {{-- Decorative diagonal gold lines --}}
    <div class="absolute inset-0 opacity-10 overflow-hidden">
        <svg class="absolute w-full h-full" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <line x1="0" y1="100%" x2="60%" y2="0" stroke="#C9A84C" stroke-width="1"/>
            <line x1="30%" y1="100%" x2="90%" y2="0" stroke="#C9A84C" stroke-width="0.5"/>
            <line x1="50%" y1="100%" x2="100%" y2="20%" stroke="#C9A84C" stroke-width="0.5"/>
        </svg>
    </div>

    {{-- Content --}}
    <div class="relative z-10 text-center px-4 max-w-5xl mx-auto" id="hero-content">
        <div class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-4">Lietuva &middot; Padelis &middot; Turnyrai</div>
        <h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-white mb-6 leading-none">
            PADELIO<br><span class="text-gold">TURNYRAI</span>
        </h1>
        <p class="text-xl md:text-2xl text-gray-400 mb-10 font-light">{{ __('messages.hero_tagline') }}</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('tournaments') }}" class="px-8 py-4 bg-gold text-dark font-bold rounded-none hover:bg-gold-light transition-colors text-lg">
                {{ __('messages.all_tournaments') }}
            </a>
            <a href="{{ route('contact') }}" class="px-8 py-4 border border-gold text-gold font-bold rounded-none hover:bg-gold hover:text-dark transition-colors text-lg">
                {{ __('messages.nav_contact') }}
            </a>
        </div>
    </div>

    {{-- Scroll indicator --}}
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-gray-500">
        <span class="text-xs tracking-widest uppercase">Scroll</span>
        <div class="w-px h-12 bg-gradient-to-b from-gold to-transparent animate-pulse"></div>
    </div>
</section>

{{-- Stats Section --}}
<section id="stats-section" class="py-24 bg-dark-card border-y border-dark-border">
    <div class="max-w-6xl mx-auto px-4">
        <h2 class="text-center text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-16">{{ __('messages.stats_section_title') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
            @foreach($stats as $stat)
            <div class="text-center" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                <div class="text-6xl md:text-7xl font-black text-white mb-3" data-target="{{ $stat->value }}">0</div>
                <div class="text-gold text-sm font-semibold tracking-widest uppercase">
                    {{ app()->getLocale() === 'en' ? $stat->label_en : $stat->label_lt }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Featured Tournament Section --}}
@if($featuredTournament)
<section class="py-24 bg-dark">
    <div class="max-w-6xl mx-auto px-4">
        <div class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-4 text-center" data-aos="fade-up">
            {{ __('messages.featured_tournament') }}
        </div>
        <div class="grid md:grid-cols-2 gap-0 items-center" data-aos="fade-up" data-aos-delay="100">
            {{-- Image side --}}
            <div class="relative overflow-hidden aspect-[4/3]">
                @if($featuredTournament->cover_image)
                    <img src="{{ Storage::url($featuredTournament->cover_image) }}" alt="" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full bg-dark-card flex items-center justify-center">
                        <svg class="w-16 h-16 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-r from-transparent to-dark/50"></div>
            </div>
            {{-- Text side --}}
            <div class="bg-dark-card p-10 md:p-14 border border-dark-border">
                @php $trans = $featuredTournament->translation(app()->getLocale()); @endphp
                <div class="text-gold text-xs tracking-widest uppercase mb-4">
                    @if($featuredTournament->status === 'active') {{ __('messages.tournament_status_active') }}
                    @elseif($featuredTournament->status === 'upcoming') {{ __('messages.tournament_status_upcoming') }}
                    @else {{ __('messages.tournament_status_past') }}
                    @endif
                </div>
                <h2 class="text-3xl md:text-4xl font-black text-white mb-4">{{ $trans?->title ?? $featuredTournament->slug }}</h2>
                <div class="text-gray-400 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $featuredTournament->date_start->format('Y-m-d') }}
                    @if($featuredTournament->date_end) &mdash; {{ $featuredTournament->date_end->format('Y-m-d') }} @endif
                </div>
                <div class="text-gray-400 mb-6 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $featuredTournament->location }}
                </div>
                @if($trans?->description)
                    <p class="text-gray-300 mb-8 leading-relaxed">{{ Str::limit($trans->description, 200) }}</p>
                @endif
                <div class="flex gap-4 flex-wrap">
                    @if($featuredTournament->registration_active && $featuredTournament->registration_url)
                        <a href="{{ $featuredTournament->registration_url }}" target="_blank" class="px-6 py-3 bg-gold text-dark font-bold hover:bg-gold-light transition-colors">
                            {{ __('messages.register_btn') }}
                        </a>
                    @endif
                    <a href="{{ route('tournament.show', $featuredTournament->slug) }}" class="px-6 py-3 border border-gold text-gold hover:bg-gold hover:text-dark transition-colors font-semibold">
                        {{ __('messages.learn_more') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- Global Sponsors Section --}}
@if($globalSponsors->count() > 0)
<section class="py-24 bg-dark-card border-t border-dark-border">
    <div class="max-w-6xl mx-auto px-4">
        <div class="text-center mb-16" data-aos="fade-up">
            <div class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-3">{{ __('messages.sponsors_section_title') }}</div>
            <p class="text-gray-500">{{ __('messages.sponsors_section_subtitle') }}</p>
        </div>

        @php
            $grouped = $globalSponsors->groupBy('category');
            $order = ['gold', 'silver', 'bronze', 'general'];
            $categoryLabels = [
                'gold' => 'Auksiniai r&#279;m&#279;jai',
                'silver' => 'Sidabriniai r&#279;m&#279;jai',
                'bronze' => 'Bronziniai r&#279;m&#279;jai',
            ];
        @endphp

        @foreach($order as $cat)
            @if(isset($grouped[$cat]) && $grouped[$cat]->count() > 0)
                <div class="mb-12" data-aos="fade-up">
                    @if($cat !== 'general')
                        <div class="text-center text-xs tracking-widest uppercase text-gray-600 mb-8">
                            {{ $categoryLabels[$cat] ?? ucfirst($cat) }}
                        </div>
                    @endif
                    <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16">
                        @foreach($grouped[$cat] as $sponsor)
                            <a href="{{ $sponsor->url ?: '#' }}" target="{{ $sponsor->url ? '_blank' : '_self' }}"
                               class="opacity-60 hover:opacity-100 transition-opacity duration-300 grayscale hover:grayscale-0">
                                <img src="{{ Storage::url($sponsor->logo) }}" alt="{{ $sponsor->name }}"
                                     class="h-12 md:h-16 w-auto object-contain max-w-[160px]">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>
@endif

@endsection
