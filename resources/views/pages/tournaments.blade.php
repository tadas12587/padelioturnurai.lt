@extends('layouts.app')

@section('title', 'Padelio Turnyrai - ' . __('messages.nav_tournaments'))

@section('content')
<div class="pt-24 pb-16 bg-dark min-h-screen">
    <div class="max-w-6xl mx-auto px-4">
        {{-- Header --}}
        <div class="py-16 text-center" data-aos="fade-up">
            <div class="text-gold text-sm tracking-[0.3em] uppercase font-semibold mb-4">{{ __('messages.tournaments_section_title') }}</div>
            <h1 class="text-5xl font-black text-white">{{ __('messages.nav_tournaments') }}</h1>
        </div>

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
                         class="bg-dark-card border border-dark-border hover:border-gold/50 transition-all duration-300 group">
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
                            <a href="{{ lroute('tournament.show', $tournament->slug) }}"
                               class="inline-block w-full text-center py-3 border border-gold text-gold hover:bg-gold hover:text-dark transition-colors font-semibold text-sm mt-2">
                                {{ __('messages.learn_more') }} &rarr;
                            </a>
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
