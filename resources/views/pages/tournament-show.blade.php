@extends('layouts.app')

@php
    $trans = $tournament->translation(app()->getLocale());
    $title = $trans?->title ?? $tournament->slug;
@endphp

@section('title', $title . ' - Padelio Turnyrai')

@section('content')

{{-- Hero with cover image --}}
<section class="relative h-[70vh] min-h-[400px] flex items-end overflow-hidden">
    @if($tournament->cover_image)
        <img src="{{ Storage::url($tournament->cover_image) }}" alt="{{ $title }}"
             class="absolute inset-0 w-full h-full object-cover">
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-dark via-[#0d1117] to-[#1a1a2e]"></div>
    @endif
    {{-- Gradient overlay --}}
    <div class="absolute inset-0 bg-gradient-to-t from-dark via-dark/60 to-transparent"></div>

    {{-- Content overlay --}}
    <div class="relative z-10 w-full max-w-6xl mx-auto px-4 pb-16">
        {{-- Status badge --}}
        <div class="mb-4">
            <span class="px-4 py-1.5 text-xs font-bold uppercase tracking-wider
                @if($tournament->status === 'active') bg-green-500 text-white
                @elseif($tournament->status === 'upcoming') bg-gold text-dark
                @else bg-gray-600 text-white
                @endif">
                @if($tournament->status === 'active') {{ __('messages.tournament_status_active') }}
                @elseif($tournament->status === 'upcoming') {{ __('messages.tournament_status_upcoming') }}
                @else {{ __('messages.tournament_status_past') }}
                @endif
            </span>
        </div>
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4 leading-tight">{{ $title }}</h1>
        <div class="flex flex-wrap items-center gap-6 text-gray-300">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ $tournament->date_start->format('Y-m-d') }}
                @if($tournament->date_end) &mdash; {{ $tournament->date_end->format('Y-m-d') }} @endif
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                {{ $tournament->location }}
            </div>
            @if($tournament->participants_count > 0)
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="text-gold font-bold">{{ $tournament->participants_count }}</span> {{ __('messages.participants') }}
            </div>
            @endif
        </div>
    </div>
</section>

{{-- Back link & Content --}}
<div class="bg-dark">
    <div class="max-w-4xl mx-auto px-4 py-12">
        {{-- Back link --}}
        <a href="{{ lroute('tournaments') }}" class="inline-flex items-center gap-2 text-gold hover:text-gold-light transition-colors text-sm font-medium mb-12">
            {{ __('messages.back_to_tournaments') }}
        </a>

        {{-- Action buttons row --}}
        <div class="flex flex-wrap gap-4 mb-12" data-aos="fade-up">

            {{-- Registration button --}}
            @if($tournament->registration_active && $tournament->registration_url)
                <a href="{{ $tournament->registration_url }}" target="_blank"
                   class="inline-flex items-center gap-2 px-8 py-4 bg-gold text-dark font-bold text-lg hover:bg-gold-light transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    {{ __('messages.register_btn') }}
                </a>
            @elseif($tournament->status !== 'past' && !$tournament->registration_active)
                <span class="inline-flex items-center px-6 py-3 border border-dark-border text-gray-500">
                    {{ __('messages.registration_closed') }}
                </span>
            @endif

            {{-- Groups / Tables button — shown only when results_url is set --}}
            @if($tournament->results_url)
                <a href="{{ $tournament->results_url }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 px-8 py-4 bg-dark-card border border-gold text-gold font-bold text-lg hover:bg-gold hover:text-dark transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 3v18M14 3v18"/>
                        <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ __('messages.groups_tables_btn') }}
                </a>
            @endif

        </div>

        {{-- Description --}}
        @if($trans?->description)
            <div class="mb-16" data-aos="fade-up">
                <div class="prose prose-invert prose-gold max-w-none text-gray-300 leading-relaxed text-lg">
                    {!! nl2br(e($trans->description)) !!}
                </div>
            </div>
        @endif

        {{-- Results --}}
        @if($tournament->results_text || $tournament->results_link)
            <div class="mb-16" data-aos="fade-up">
                <h2 class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-6">{{ __('messages.results') }}</h2>

                @if($tournament->results_link)
                    {{-- External results link --}}
                    <a href="{{ $tournament->results_link }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-3 px-6 py-4 bg-dark-card border border-dark-border text-white hover:border-gold hover:text-gold transition-colors">
                        <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        {{ __('messages.results') }} →
                    </a>
                @endif

                @if($tournament->results_text)
                    {{-- Text results --}}
                    <div class="bg-dark-card border border-dark-border p-8 {{ $tournament->results_link ? 'mt-6' : '' }}">
                        <div class="text-gray-300 leading-relaxed whitespace-pre-line font-mono text-sm">{{ $tournament->results_text }}</div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Photo Gallery --}}
    @if($tournament->photos->count() > 0)
        @php
            $previewLimit = 8;
            $allPhotos    = $tournament->photos->sortBy('sort_order')->values();
            $previewPhotos = $allPhotos->take($previewLimit);
            $remaining    = $allPhotos->count() - $previewLimit;
        @endphp
        <div class="max-w-6xl mx-auto px-4 pb-16" data-aos="fade-up">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-gold text-sm font-semibold tracking-[0.3em] uppercase">{{ __('messages.gallery') }}</h2>
                @if($tournament->photos->count() > 0)
                    <span class="text-gray-500 text-sm">{{ $tournament->photos->count() }} {{ __('messages.gallery_photos_count') }}</span>
                @endif
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                {{-- Visible preview photos (all go into lightbox) --}}
                @foreach($allPhotos as $i => $photo)
                    <a href="{{ Storage::url($photo->path) }}"
                       class="glightbox relative overflow-hidden aspect-square group {{ $i >= $previewLimit ? 'hidden' : '' }}"
                       data-gallery="tournament-gallery">
                        <img src="{{ Storage::url($photo->path) }}" alt=""
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                             loading="lazy">
                        <div class="absolute inset-0 bg-dark/0 group-hover:bg-dark/30 transition-colors duration-300 flex items-center justify-center">
                            <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                            </svg>
                        </div>
                        {{-- "+N daugiau" overlay on 8th tile if there are more --}}
                        @if($i === $previewLimit - 1 && $remaining > 0)
                            <div class="absolute inset-0 bg-dark/70 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-white text-3xl font-black">+{{ $remaining }}</span>
                                <span class="text-gray-300 text-xs mt-1 tracking-widest uppercase">{{ __('messages.gallery_more') }}</span>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>

            {{-- Bottom row: show all in lightbox + optional external gallery --}}
            @if($remaining > 0 || $tournament->gallery_url)
                <div class="flex flex-wrap gap-4 mt-6">
                    @if($remaining > 0)
                        <button id="gallery-show-all"
                                class="inline-flex items-center gap-2 px-6 py-3 border border-dark-border text-gray-300 hover:border-gold hover:text-gold transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                            {{ __('messages.gallery_show_all') }} ({{ $allPhotos->count() }})
                        </button>
                    @endif

                    @if($tournament->gallery_url)
                        <a href="{{ $tournament->gallery_url }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 px-6 py-3 bg-dark-card border border-gold text-gold hover:bg-gold hover:text-dark transition-colors text-sm font-semibold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ __('messages.gallery_full') }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Tournament Sponsors --}}
    @if($tournament->sponsors->count() > 0)
        <div class="max-w-6xl mx-auto px-4 pb-24" data-aos="fade-up">
            <h2 class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-8">{{ __('messages.tournament_sponsors') }}</h2>
            @php
                $sponsorsByCategory = $tournament->sponsors->groupBy('category');
                $catOrder = ['gold', 'silver', 'bronze', 'general'];
            @endphp
            <div class="bg-dark-card border border-dark-border p-10 space-y-8">
                @foreach($catOrder as $cat)
                    @if(isset($sponsorsByCategory[$cat]) && $sponsorsByCategory[$cat]->count() > 0)
                        <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16">
                            @foreach($sponsorsByCategory[$cat] as $sponsor)
                                <a href="{{ $sponsor->url ?: '#' }}" target="{{ $sponsor->url ? '_blank' : '_self' }}"
                                   class="opacity-60 hover:opacity-100 transition-opacity duration-300 grayscale hover:grayscale-0">
                                    <img src="{{ Storage::url($sponsor->logo) }}" alt="{{ $sponsor->name }}"
                                         class="h-12 md:h-16 w-auto object-contain max-w-[160px]">
                                </a>
                            @endforeach
                        </div>
                        @if(!$loop->last)
                            <div class="border-t border-dark-border"></div>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
    const lightbox = GLightbox({ selector: '.glightbox' });

    // "Show all" button — reveals hidden photos and re-inits lightbox
    const showAllBtn = document.getElementById('gallery-show-all');
    if (showAllBtn) {
        showAllBtn.addEventListener('click', function () {
            // Remove overlay from 8th tile
            document.querySelectorAll('.glightbox .bg-dark\\/70').forEach(el => el.remove());

            // Show hidden tiles
            document.querySelectorAll('.glightbox.hidden').forEach(el => el.classList.remove('hidden'));

            // Hide the button
            showAllBtn.style.display = 'none';

            // Re-init lightbox so new items are included
            lightbox.destroy();
            window._lb = GLightbox({ selector: '.glightbox' });
        });
    }
</script>
@endpush
