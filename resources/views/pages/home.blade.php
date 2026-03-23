@extends('layouts.app')

@section('title', 'Padelio Turnyrai - ' . __('messages.nav_home'))

@section('content')

{{-- Hero Section --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">

    {{-- Background photos slideshow --}}
    @if($heroPhotos->count() > 0)
        <div class="absolute inset-0 z-0" id="hero-slider">
            @foreach($heroPhotos as $i => $photo)
                <div class="hero-slide absolute inset-0 transition-opacity duration-[2000ms] ease-in-out {{ $i === 0 ? 'opacity-100' : 'opacity-0' }}">
                    <img src="{{ $photo }}" alt=""
                         class="w-full h-full object-cover object-center scale-105"
                         style="transform-origin: center;">
                </div>
            @endforeach
        </div>
    @endif

    {{-- Dark overlay --}}
    <div class="absolute inset-0 z-10" style="background: linear-gradient(to bottom, rgba(10,10,15,0.65) 0%, rgba(10,10,15,0.45) 50%, rgba(10,10,15,0.85) 100%);"></div>

    {{-- Decorative diagonal gold lines --}}
    <div class="absolute inset-0 z-10 opacity-10 overflow-hidden pointer-events-none">
        <svg class="absolute w-full h-full" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <line x1="0" y1="100%" x2="60%" y2="0" stroke="#C9A84C" stroke-width="1"/>
            <line x1="30%" y1="100%" x2="90%" y2="0" stroke="#C9A84C" stroke-width="0.5"/>
            <line x1="50%" y1="100%" x2="100%" y2="20%" stroke="#C9A84C" stroke-width="0.5"/>
        </svg>
    </div>

    {{-- Content --}}
    <div class="relative z-20 text-center px-4 max-w-5xl mx-auto" id="hero-content">
        <div class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-4">Lietuva &middot; Padelis &middot; Turnyrai</div>
        <h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-white mb-6 leading-none drop-shadow-2xl">
            PADELIO<br><span class="text-gold">TURNYRAI</span>
        </h1>
        <p class="text-xl md:text-2xl text-gray-300 mb-10 font-light drop-shadow-lg">{{ __('messages.hero_tagline') }}</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ lroute('tournaments') }}" class="px-8 py-4 bg-gold text-dark font-bold rounded-none hover:bg-gold-light transition-colors text-lg">
                {{ __('messages.all_tournaments') }}
            </a>
            <a href="{{ lroute('contact') }}" class="px-8 py-4 border border-gold text-gold font-bold rounded-none hover:bg-gold hover:text-dark transition-colors text-lg">
                {{ __('messages.nav_contact') }}
            </a>
        </div>
    </div>

    {{-- Scroll indicator --}}
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center gap-3 cursor-pointer" onclick="document.getElementById('stats-section').scrollIntoView({behavior:'smooth'})">
        <div class="flex flex-col items-center gap-1 text-gold opacity-70 hover:opacity-100 transition-opacity">
            <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
            </svg>
            <svg class="w-5 h-5 animate-bounce" style="animation-delay:0.15s" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function() {
    const slides = document.querySelectorAll('.hero-slide');
    if (slides.length < 2) return;
    let current = 0;
    setInterval(() => {
        slides[current].classList.remove('opacity-100');
        slides[current].classList.add('opacity-0');
        current = (current + 1) % slides.length;
        slides[current].classList.remove('opacity-0');
        slides[current].classList.add('opacity-100');
    }, 5000);
})();
</script>
@endpush

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

{{-- Gold Sponsors Block (after stats) --}}
@if($goldSponsors->count() > 0)
<section class="py-16 bg-dark border-b border-dark-border">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex flex-wrap justify-center items-center gap-10 md:gap-20" data-aos="fade-up">
            @foreach($goldSponsors as $sponsor)
                <a href="{{ $sponsor->url ?: '#' }}" target="{{ $sponsor->url ? '_blank' : '_self' }}"
                   class="opacity-70 hover:opacity-100 transition-opacity duration-300 grayscale hover:grayscale-0">
                    <img src="{{ Storage::url($sponsor->logo) }}" alt="{{ $sponsor->name }}"
                         class="h-14 md:h-20 w-auto object-contain max-w-[200px]">
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Tournaments Section --}}
@if($tournaments->count() > 0)
<section class="py-24 bg-dark">
    <div class="max-w-6xl mx-auto px-4">
        <div class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-16 text-center" data-aos="fade-up">
            {{ __('messages.featured_tournament') }}
        </div>
        <div class="space-y-8">
            @foreach($tournaments as $tournament)
            @php $trans = $tournament->translation(app()->getLocale()); @endphp
            <div class="grid md:grid-cols-2 gap-0 items-center" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                @if($loop->even)
                {{-- Image left --}}
                <div class="relative overflow-hidden aspect-[4/3]">
                    @if($tournament->cover_image)
                        <img src="{{ Storage::url($tournament->cover_image) }}" alt="" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full bg-dark-card flex items-center justify-center min-h-[240px]">
                            <svg class="w-16 h-16 text-gold opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent to-dark/40"></div>
                </div>
                <div class="bg-dark-card p-10 md:p-12 border border-dark-border">
                @else
                {{-- Text left --}}
                <div class="bg-dark-card p-10 md:p-12 border border-dark-border order-2 md:order-1">
                @endif
                    <div class="text-gold text-xs tracking-widest uppercase mb-4">
                        @if($tournament->status === 'active') {{ __('messages.tournament_status_active') }}
                        @elseif($tournament->status === 'upcoming') {{ __('messages.tournament_status_upcoming') }}
                        @else {{ __('messages.tournament_status_past') }}
                        @endif
                    </div>
                    <h2 class="text-2xl md:text-3xl font-black text-white mb-4">{{ $trans?->title ?? $tournament->slug }}</h2>
                    <div class="text-gray-400 mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $tournament->date_start->format('Y-m-d') }}
                        @if($tournament->date_end) &mdash; {{ $tournament->date_end->format('Y-m-d') }} @endif
                    </div>
                    <div class="text-gray-400 mb-6 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $tournament->location }}
                    </div>
                    @if($trans?->description)
                        <p class="text-gray-300 mb-6 leading-relaxed">{{ Str::limit($trans->description, 160) }}</p>
                    @endif
                    <div class="flex gap-4 flex-wrap">
                        @if($tournament->registration_active && $tournament->registration_url)
                            <a href="{{ $tournament->registration_url }}" target="_blank" class="px-6 py-3 bg-gold text-dark font-bold hover:bg-gold-light transition-colors">
                                {{ __('messages.register_btn') }}
                            </a>
                        @endif
                        <a href="{{ lroute('tournament.show', $tournament->slug) }}" class="px-6 py-3 border border-gold text-gold hover:bg-gold hover:text-dark transition-colors font-semibold">
                            {{ __('messages.learn_more') }}
                        </a>
                    </div>
                </div>
                @if($loop->odd)
                <div class="relative overflow-hidden aspect-[4/3] order-1 md:order-2">
                    @if($tournament->cover_image)
                        <img src="{{ Storage::url($tournament->cover_image) }}" alt="" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full bg-dark-card flex items-center justify-center min-h-[240px]">
                            <svg class="w-16 h-16 text-gold opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-l from-transparent to-dark/40"></div>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- All Tournaments Button --}}
        <div class="text-center mt-16" data-aos="fade-up">
            <a href="{{ lroute('tournaments') }}" class="px-10 py-4 border border-gold text-gold font-bold hover:bg-gold hover:text-dark transition-colors text-lg tracking-widest uppercase">
                {{ __('messages.all_tournaments') }}
            </a>
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
                <div class="mb-10" data-aos="fade-up">
                    <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16">
                        @foreach($grouped[$cat] as $sponsor)
                            <a href="{{ $sponsor->url ?: '#' }}" target="{{ $sponsor->url ? '_blank' : '_self' }}"
                               class="opacity-60 hover:opacity-100 transition-opacity duration-300 grayscale hover:grayscale-0">
                                <img src="{{ Storage::url($sponsor->logo) }}" alt="{{ $sponsor->name }}"
                                     class="h-12 md:h-16 w-auto object-contain max-w-[160px]">
                            </a>
                        @endforeach
                    </div>
                    @if(!$loop->last)
                        <div class="border-t border-dark-border mt-10"></div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</section>
@endif

@endsection
