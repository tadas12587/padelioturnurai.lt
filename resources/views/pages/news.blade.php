@extends('layouts.app')

@section('title', __('messages.nav_news') . ' - Padelio Turnyrai')

@section('og_title', __('messages.nav_news') . ' — Padelio Turnyrai')
@section('og_description', 'Padelio turnyrai Lietuvoje – naujausios naujienos, turnyrų rezultatai ir reitingų atnaujinimai.')

@section('content')
<div class="pt-24 pb-16 bg-dark min-h-screen">
    <div class="max-w-6xl mx-auto px-4">

        {{-- Header --}}
        <div class="py-16 text-center" data-aos="fade-up">
            <div class="text-gold text-sm tracking-[0.3em] uppercase font-semibold mb-4">{{ __('messages.nav_news') }}</div>
            <h1 class="text-5xl font-black text-white">{{ __('messages.nav_news') }}</h1>
        </div>

        @if(!$featured && $news->isEmpty())
            {{-- Empty state --}}
            <div class="text-center text-gray-500 py-20" data-aos="fade-up">
                <svg class="w-16 h-16 mx-auto mb-4 text-gold/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h2"/>
                </svg>
                <p class="text-xl">{{ __('messages.news_empty') }}</p>
            </div>
        @else

            {{-- Featured article --}}
            @if($featured)
                @php $featuredTrans = $featured->translation(app()->getLocale()); @endphp
                <div class="mb-16" data-aos="fade-up">
                    <a href="{{ lroute('news.show', ['slug' => $featured->slug]) }}"
                       class="group block bg-dark-card border border-dark-border hover:border-gold/50 transition-all duration-300">

                        {{-- Cover image --}}
                        <div class="relative aspect-video md:aspect-[21/9] overflow-hidden">
                            @if($featured->cover_image)
                                <img src="{{ Storage::url($featured->cover_image) }}" alt="{{ $featuredTrans?->title }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-dark via-[#0d1117] to-[#1a1a2e]"></div>
                            @endif
                            {{-- Gradient overlay (desktop only — content is below on mobile) --}}
                            <div class="absolute inset-0 bg-gradient-to-t from-dark via-dark/50 to-transparent hidden md:block"></div>

                            {{-- Content overlay — desktop only --}}
                            <div class="absolute inset-0 items-end hidden md:flex">
                                <div class="p-12 w-full">
                                    <div class="flex items-center gap-3 mb-4">
                                        <span class="px-3 py-1 bg-gold text-dark text-xs font-bold uppercase tracking-wider">
                                            {{ app()->getLocale() === 'en' ? 'Featured' : 'Rekomenduojama' }}
                                        </span>
                                        @if($featured->tournament)
                                            <span class="px-3 py-1 bg-dark-card/80 border border-dark-border text-gold text-xs font-semibold uppercase tracking-wide">
                                                {{ $featured->tournament->translation(app()->getLocale())?->title ?? $featured->tournament->slug }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($featured->published_at)
                                        <div class="text-gray-400 text-sm mb-3">
                                            {{ $featured->published_at->format('Y-m-d') }}
                                            &nbsp;&bull;&nbsp;
                                            {{ __('messages.news_reading_time', ['min' => $featured->readingTime(app()->getLocale())]) }}
                                        </div>
                                    @endif
                                    <h2 class="text-3xl md:text-4xl font-black text-white mb-4 leading-tight group-hover:text-gold transition-colors">
                                        {{ $featuredTrans?->title ?? $featured->slug }}
                                    </h2>
                                    @if($featuredTrans?->excerpt)
                                        <p class="text-gray-300 text-lg mb-6 max-w-2xl line-clamp-2">
                                            {{ $featuredTrans->excerpt }}
                                        </p>
                                    @endif
                                    <span class="inline-flex items-center gap-2 text-gold font-semibold group-hover:gap-3 transition-all">
                                        {{ __('messages.news_read_more') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Content block — mobile only --}}
                        <div class="md:hidden p-6">
                            <div class="flex items-center gap-2 mb-3 flex-wrap">
                                <span class="px-3 py-1 bg-gold text-dark text-xs font-bold uppercase tracking-wider">
                                    {{ app()->getLocale() === 'en' ? 'Featured' : 'Rekomenduojama' }}
                                </span>
                                @if($featured->tournament)
                                    <span class="px-2 py-1 border border-gold/50 text-gold text-xs font-semibold uppercase tracking-wide">
                                        {{ $featured->tournament->translation(app()->getLocale())?->title ?? $featured->tournament->slug }}
                                    </span>
                                @endif
                            </div>
                            @if($featured->published_at)
                                <div class="text-gray-500 text-xs mb-2">
                                    {{ $featured->published_at->format('Y-m-d') }}
                                    &nbsp;&bull;&nbsp;
                                    {{ __('messages.news_reading_time', ['min' => $featured->readingTime(app()->getLocale())]) }}
                                </div>
                            @endif
                            <h2 class="text-xl font-black text-white mb-3 leading-snug group-hover:text-gold transition-colors">
                                {{ $featuredTrans?->title ?? $featured->slug }}
                            </h2>
                            @if($featuredTrans?->excerpt)
                                <p class="text-gray-400 text-sm line-clamp-3 mb-4 leading-relaxed">
                                    {{ $featuredTrans->excerpt }}
                                </p>
                            @endif
                            <span class="inline-flex items-center gap-1 text-gold text-sm font-semibold">
                                {{ __('messages.news_read_more') }}
                            </span>
                        </div>

                    </a>
                </div>
            @endif

            {{-- News grid --}}
            @if($news->count() > 0)
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($news as $article)
                        @php $trans = $article->translation(app()->getLocale()); @endphp
                        <div data-aos="fade-up" data-aos-delay="{{ $loop->index * 50 }}">
                            <a href="{{ lroute('news.show', ['slug' => $article->slug]) }}"
                               class="group block bg-dark-card border border-dark-border hover:border-gold/50 transition-all duration-300 h-full">

                                {{-- Cover image --}}
                                <div class="relative overflow-hidden aspect-video">
                                    @if($article->cover_image)
                                        <img src="{{ Storage::url($article->cover_image) }}" alt="{{ $trans?->title }}"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    @else
                                        <div class="w-full h-full bg-dark flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gold/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h2"/>
                                            </svg>
                                        </div>
                                    @endif
                                    {{-- Tournament badge --}}
                                    @if($article->tournament)
                                        <div class="absolute top-3 left-3">
                                            <span class="px-2 py-1 bg-dark/80 border border-gold/50 text-gold text-xs font-semibold uppercase tracking-wide">
                                                {{ $article->tournament->translation(app()->getLocale())?->title ?? $article->tournament->slug }}
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Card body --}}
                                <div class="p-6">
                                    {{-- Date + reading time --}}
                                    <div class="flex items-center gap-3 text-gray-500 text-xs mb-3">
                                        @if($article->published_at)
                                            <span>{{ $article->published_at->format('Y-m-d') }}</span>
                                            <span>&bull;</span>
                                        @endif
                                        <span>{{ __('messages.news_reading_time', ['min' => $article->readingTime(app()->getLocale())]) }}</span>
                                    </div>

                                    {{-- Title --}}
                                    <h3 class="text-lg font-bold text-white mb-2 group-hover:text-gold transition-colors line-clamp-2 leading-snug">
                                        {{ $trans?->title ?? $article->slug }}
                                    </h3>

                                    {{-- Excerpt --}}
                                    @if($trans?->excerpt)
                                        <p class="text-gray-500 text-sm line-clamp-2 mb-4 leading-relaxed">
                                            {{ $trans->excerpt }}
                                        </p>
                                    @endif

                                    <span class="inline-flex items-center gap-1 text-gold text-sm font-semibold group-hover:gap-2 transition-all">
                                        {{ __('messages.news_read_more') }}
                                    </span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($news->hasPages())
                    <div class="mt-12 flex justify-center" data-aos="fade-up">
                        {{ $news->links() }}
                    </div>
                @endif
            @endif

        @endif

    </div>
</div>
@endsection
