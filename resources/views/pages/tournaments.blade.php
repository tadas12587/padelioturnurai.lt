@extends('layouts.app')

@section('title', 'Padelio Turnyrai - ' . __('messages.nav_tournaments'))

@section('og_title', 'Turnyrai — Padelio Turnyrai')
@section('og_description', 'Visi Lietuvos padelio turnyrai vienoje vietoje. Registracija, tvarkaraštis, rezultatai ir reitingai.')

@section('content')
<div class="pt-24 pb-16 bg-dark min-h-screen">
    <div class="max-w-6xl mx-auto px-4">
        {{-- Header --}}
        <div class="py-16 text-center" data-aos="fade-up">
            <div class="text-gold text-sm tracking-[0.3em] uppercase font-semibold mb-4">{{ __('messages.tournaments_section_title') }}</div>
            <h1 class="text-5xl font-black text-white">{{ __('messages.nav_tournaments') }}</h1>
        </div>

        @php
            $featuredTournament = $tournaments->first(
                fn($t) => $t->status === 'active' && $t->registration_active && $t->registration_url
            );
            $featuredTrans = $featuredTournament?->translation(app()->getLocale());
        @endphp

        {{-- Featured Active Tournament --}}
        @if($featuredTournament)
        <div class="mb-16" data-aos="fade-up">
            {{-- Section label --}}
            <div class="flex items-center gap-3 mb-6">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-gold opacity-60"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gold"></span>
                </span>
                <span class="text-gold text-xs font-bold tracking-[0.25em] uppercase">{{ __('messages.featured_tournament') }}</span>
                <div class="flex-1 h-px bg-gold/20"></div>
            </div>

            <div class="grid md:grid-cols-2 border border-gold/50 hover:border-gold/80 transition-colors">
                {{-- Cover image --}}
                <div class="relative overflow-hidden min-h-[260px] md:min-h-[380px]">
                    @if($featuredTournament->cover_image)
                        <img src="{{ Storage::url($featuredTournament->cover_image) }}"
                             alt="{{ $featuredTrans?->title }}"
                             class="w-full h-full object-cover absolute inset-0">
                    @else
                        <div class="absolute inset-0 bg-dark-card flex items-center justify-center">
                            <svg class="w-20 h-20 text-gold/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-transparent to-dark/60"></div>
                    {{-- Active badge --}}
                    <div class="absolute top-4 left-4">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-500 text-white text-xs font-bold uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            {{ __('messages.tournament_status_active') }}
                        </span>
                    </div>
                </div>

                {{-- Info --}}
                <div class="bg-dark-card p-8 md:p-12 flex flex-col justify-center">
                    <h2 class="text-3xl md:text-4xl font-black text-white mb-6 leading-tight">
                        {{ $featuredTrans?->title ?? $featuredTournament->slug }}
                    </h2>

                    <div class="space-y-2 mb-6">
                        <div class="flex items-center gap-2 text-gray-400 text-sm">
                            <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ $featuredTournament->date_start->format('Y-m-d') }}
                            @if($featuredTournament->date_end) &mdash; {{ $featuredTournament->date_end->format('Y-m-d') }} @endif
                        </div>
                        <div class="flex items-center gap-2 text-gray-400 text-sm">
                            <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $featuredTournament->location }}
                        </div>
                        @if($featuredTournament->participants_count > 0)
                        <div class="flex items-center gap-2 text-gray-400 text-sm">
                            <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="text-gold font-bold">{{ $featuredTournament->participants_count }}</span>
                            &nbsp;{{ __('messages.participants') }}
                        </div>
                        @endif
                    </div>

                    @if($featuredTrans?->description)
                        <p class="text-gray-300 mb-8 leading-relaxed text-sm">
                            {{ Str::limit($featuredTrans->description, 220) }}
                        </p>
                    @endif

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ $featuredTournament->registration_url }}" target="_blank"
                           class="px-8 py-4 bg-gold text-dark font-bold hover:bg-gold-light transition-colors text-base">
                            {{ __('messages.register_btn') }} &rarr;
                        </a>
                        <a href="{{ lroute('tournament.show', ['slug' => $featuredTournament->slug]) }}"
                           class="px-8 py-4 border border-gold text-gold font-bold hover:bg-gold hover:text-dark transition-colors text-base">
                            {{ __('messages.learn_more') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Filter tabs (Alpine) --}}
        <div x-data="{ filter: 'all' }" class="mt-4">
            <div class="flex gap-2 mb-12 flex-wrap justify-center">
                @foreach(['all' => 'messages.filter_all', 'upcoming' => 'messages.filter_upcoming', 'active' => 'messages.filter_active', 'past' => 'messages.filter_past'] as $val => $key)
                <button @click="filter = '{{ $val }}'"
                        :class="filter === '{{ $val }}' ? 'bg-gold text-dark' : 'border border-dark-border text-gray-400 hover:border-gold hover:text-gold'"
                        class="px-6 py-2 font-semibold text-sm tracking-wide transition-colors">
                    {{ __($key) }}
                </button>
                @endforeach
            </div>

            {{-- Tournament grid --}}
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($tournaments as $tournament)
                    @php $trans = $tournament->translation(app()->getLocale()); @endphp
                    <div x-show="filter === 'all' || filter === '{{ $tournament->status }}'"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="bg-dark-card border transition-all duration-300 group
                             {{ $tournament->status === 'active' && $tournament->registration_active ? 'border-gold/50 hover:border-gold' : 'border-dark-border hover:border-gold/50' }}">
                        {{-- Cover image --}}
                        <div class="relative overflow-hidden aspect-[16/9]">
                            @if($tournament->cover_image)
                                <img src="{{ Storage::url($tournament->cover_image) }}" alt="{{ $trans?->title }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="w-full h-full bg-dark flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gold/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                            @endif
                            {{-- Status badge --}}
                            <div class="absolute top-3 left-3">
                                <span class="px-3 py-1 text-xs font-bold uppercase tracking-wider
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
                        </div>
                        {{-- Card body --}}
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-white mb-3 group-hover:text-gold transition-colors">
                                {{ $trans?->title ?? $tournament->slug }}
                            </h3>
                            <div class="flex items-center gap-2 text-gray-500 text-sm mb-2">
                                <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                {{ $tournament->date_start->format('Y-m-d') }}
                                @if($tournament->date_end) &mdash; {{ $tournament->date_end->format('Y-m-d') }} @endif
                            </div>
                            <div class="flex items-center gap-2 text-gray-500 text-sm mb-4">
                                <svg class="w-4 h-4 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $tournament->location }}
                            </div>
                            @if($tournament->participants_count > 0)
                            <div class="text-gray-500 text-sm mb-4">
                                <span class="text-gold font-bold">{{ $tournament->participants_count }}</span> {{ __('messages.participants') }}
                            </div>
                            @endif
                            <a href="{{ lroute('tournament.show', ['slug' => $tournament->slug]) }}"
                               class="inline-block w-full text-center py-3 border border-gold text-gold hover:bg-gold hover:text-dark transition-colors font-semibold text-sm mt-2">
                                {{ __('messages.learn_more') }} &rarr;
                            </a>

                            {{-- Notify me — controlled by admin toggle --}}
                            @if($tournament->notify_enabled && $tournament->status === 'upcoming' && !$tournament->registration_active)
                                <div class="mt-2">
                                    @include('partials.notify-btn', [
                                        'tournamentId'   => $tournament->id,
                                        'tournamentName' => $trans?->title ?? $tournament->slug,
                                        'compact'        => true,
                                    ])
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center text-gray-500 py-20">
                        {{ app()->getLocale() === 'en' ? 'No tournaments yet.' : 'Kol kas turnyru nera.' }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
