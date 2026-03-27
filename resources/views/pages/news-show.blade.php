@extends('layouts.app')

@php
    $trans = $news->translation(app()->getLocale());
    $title = $trans?->title ?? $news->slug;
@endphp

@section('title', $title . ' - ' . __('messages.nav_news'))

@php
    $ogNewsDesc = $trans?->excerpt
        ? Str::limit(strip_tags($trans->excerpt), 160)
        : ($trans?->content
            ? Str::limit(strip_tags($trans->content), 160)
            : $title . ' — padelio naujiena iš padelioturnyrai.lt');
@endphp
@section('og_type', 'article')
@section('og_title', $title . ' — Padelio Turnyrai')
@section('og_description', $ogNewsDesc)
@if($news->cover_image)
    @section('og_image', asset('storage/' . $news->cover_image))
@endif

@push('styles')
<style>
    .news-content h2 { color: white; font-size: 1.75rem; font-weight: 800; margin: 2rem 0 1rem; }
    .news-content h3 { color: #C9A84C; font-size: 1.25rem; font-weight: 700; margin: 1.5rem 0 0.75rem; }
    .news-content p { color: #D1D5DB; line-height: 1.8; margin-bottom: 1.25rem; }
    .news-content ul, .news-content ol { color: #D1D5DB; padding-left: 1.5rem; margin-bottom: 1.25rem; }
    .news-content li { margin-bottom: 0.5rem; line-height: 1.7; }
    .news-content blockquote { border-left: 4px solid #C9A84C; padding: 1rem 1.5rem; margin: 1.5rem 0; background: #111118; color: #9CA3AF; font-style: italic; }
    .news-content a { color: #C9A84C; text-decoration: underline; }
    .news-content strong { color: white; font-weight: 700; }
</style>
@endpush

@section('content')

{{-- Hero with cover image --}}
<section class="relative h-[60vh] min-h-[380px] flex items-end overflow-hidden">
    @if($news->cover_image)
        <img src="{{ Storage::url($news->cover_image) }}" alt="{{ $title }}"
             class="absolute inset-0 w-full h-full object-cover">
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-dark via-[#0d1117] to-[#1a1a2e]"></div>
    @endif
    {{-- Gradient overlay --}}
    <div class="absolute inset-0 bg-gradient-to-t from-dark via-dark/60 to-transparent"></div>

    {{-- Content overlay --}}
    <div class="relative z-10 w-full max-w-4xl mx-auto px-4 pb-12">
        {{-- Badges --}}
        <div class="flex flex-wrap items-center gap-3 mb-4">
            @if($news->is_featured)
                <span class="px-3 py-1 bg-gold text-dark text-xs font-bold uppercase tracking-wider">
                    {{ app()->getLocale() === 'en' ? 'Featured' : 'Rekomenduojama' }}
                </span>
            @endif
            @if($news->tournament)
                <a href="{{ lroute('tournament.show', ['slug' => $news->tournament->slug]) }}"
                   class="px-3 py-1 bg-dark-card/80 border border-gold/50 text-gold text-xs font-semibold uppercase tracking-wide hover:bg-gold hover:text-dark transition-colors">
                    {{ $news->tournament->translation(app()->getLocale())?->title ?? $news->tournament->slug }}
                </a>
            @endif
        </div>

        {{-- Title --}}
        <h1 class="text-3xl md:text-5xl font-black text-white mb-4 leading-tight">{{ $title }}</h1>

        {{-- Meta --}}
        <div class="flex flex-wrap items-center gap-4 text-gray-400 text-sm">
            @if($news->published_at)
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ $news->published_at->format('Y-m-d') }}
                </div>
            @endif
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('messages.news_reading_time', ['min' => $news->readingTime(app()->getLocale())]) }}
            </div>
        </div>
    </div>
</section>

{{-- Main content --}}
<div class="bg-dark">
    <div class="max-w-4xl mx-auto px-4 py-12">

        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-10" data-aos="fade-up">
            <a href="{{ lroute('home') }}" class="hover:text-gold transition-colors">{{ __('messages.nav_home') }}</a>
            <span class="text-dark-border">/</span>
            <a href="{{ lroute('news.index') }}" class="hover:text-gold transition-colors">{{ __('messages.nav_news') }}</a>
            <span class="text-dark-border">/</span>
            <span class="text-gray-400 truncate max-w-[200px]">{{ $title }}</span>
        </nav>

        {{-- Notify me — when news is linked to an upcoming tournament with notify enabled --}}
        @if($news->tournament && $news->tournament->notify_enabled && $news->tournament->status === 'upcoming' && !$news->tournament->registration_active)
            <div class="mb-8" data-aos="fade-up">
                <div class="border border-gold/20 bg-gold/5 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <p class="text-gold text-xs font-bold uppercase tracking-widest mb-1">
                            {{ $news->tournament->translation(app()->getLocale())?->title ?? $news->tournament->slug }}
                        </p>
                        <p class="text-white font-semibold text-sm">
                            {{ app()->getLocale() === 'en'
                                ? 'Registration is not yet open for this tournament.'
                                : 'Registracija šiam turnyrui dar neatidaryta.' }}
                        </p>
                    </div>
                    @include('partials.notify-btn', [
                        'tournamentId'   => $news->tournament->id,
                        'tournamentName' => $news->tournament->translation(app()->getLocale())?->title ?? $news->tournament->slug,
                        'compact'        => false,
                    ])
                </div>
            </div>
        @endif

        {{-- Excerpt (lead paragraph) --}}
        @if($trans?->excerpt)
            <div class="text-xl text-gray-300 leading-relaxed mb-10 border-l-4 border-gold pl-6" data-aos="fade-up">
                {{ $trans->excerpt }}
            </div>
        @endif

        {{-- Article content --}}
        @if($trans?->content)
            <div class="news-content mb-12" data-aos="fade-up">
                {!! $trans->content !!}
            </div>
        @endif

        {{-- Buttons --}}
        @if(!empty($news->buttons))
            <div class="flex flex-wrap gap-4 mb-12" data-aos="fade-up">
                @foreach($news->buttons as $btn)
                    @php
                        $label = app()->getLocale() === 'en' && !empty($btn['label_en'])
                            ? $btn['label_en']
                            : ($btn['label_lt'] ?? '');
                        $style = $btn['style'] ?? 'primary';
                    @endphp
                    @if(!empty($btn['url']) && !empty($label))
                        <a href="{{ $btn['url'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 px-8 py-4 font-bold text-sm transition-colors
                               @if($style === 'primary') bg-gold text-dark hover:bg-gold/80
                               @elseif($style === 'outline') border border-gold text-gold hover:bg-gold hover:text-dark
                               @else bg-dark-card border border-dark-border text-gray-300 hover:border-gold hover:text-gold
                               @endif">
                            {{ $label }}
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

    </div>

    {{-- Photo gallery --}}
    @if(!empty($news->photo_paths) && count($news->photo_paths) > 0)
        @php
            $previewLimit  = 8;
            $allPhotos     = $news->photo_paths;
            $previewPhotos = array_slice($allPhotos, 0, $previewLimit);
            $remaining     = count($allPhotos) - $previewLimit;
        @endphp
        <div class="max-w-6xl mx-auto px-4 pb-16" data-aos="fade-up">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-gold text-sm font-semibold tracking-[0.3em] uppercase">{{ __('messages.gallery') }}</h2>
                <span class="text-gray-500 text-sm">{{ count($allPhotos) }} {{ __('messages.gallery_photos_count') }}</span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($allPhotos as $i => $photoPath)
                    <a href="{{ Storage::url($photoPath) }}"
                       class="glightbox relative overflow-hidden aspect-square group {{ $i >= $previewLimit ? 'hidden' : '' }}"
                       data-gallery="news-gallery">
                        <img src="{{ Storage::url($photoPath) }}" alt=""
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                             loading="lazy">
                        <div class="absolute inset-0 bg-dark/0 group-hover:bg-dark/30 transition-colors duration-300 flex items-center justify-center">
                            <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                            </svg>
                        </div>
                        @if($i === $previewLimit - 1 && $remaining > 0)
                            <div class="absolute inset-0 bg-dark/70 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-white text-3xl font-black">+{{ $remaining }}</span>
                                <span class="text-gray-300 text-xs mt-1 tracking-widest uppercase">{{ __('messages.gallery_more') }}</span>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>

            @if($remaining > 0)
                <div class="mt-6">
                    <button id="news-gallery-show-all"
                            class="inline-flex items-center gap-2 px-6 py-3 border border-dark-border text-gray-300 hover:border-gold hover:text-gold transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                        {{ __('messages.gallery_show_all') }} ({{ count($allPhotos) }})
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Back to news link --}}
    <div class="max-w-4xl mx-auto px-4 pb-8">
        <a href="{{ lroute('news.index') }}"
           class="inline-flex items-center gap-2 text-gold hover:text-gold/80 transition-colors text-sm font-medium">
            {{ __('messages.news_back') }}
        </a>
    </div>

    {{-- Related news --}}
    @if($related->count() > 0)
        <div class="max-w-6xl mx-auto px-4 pb-24" data-aos="fade-up">
            <h2 class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-8">{{ __('messages.news_related') }}</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($related as $article)
                    @php $relTrans = $article->translation(app()->getLocale()); @endphp
                    <a href="{{ lroute('news.show', ['slug' => $article->slug]) }}"
                       class="group block bg-dark-card border border-dark-border hover:border-gold/50 transition-all duration-300">
                        <div class="relative overflow-hidden aspect-video">
                            @if($article->cover_image)
                                <img src="{{ Storage::url($article->cover_image) }}" alt="{{ $relTrans?->title }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="w-full h-full bg-dark flex items-center justify-center">
                                    <svg class="w-10 h-10 text-gold/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h2"/>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="p-5">
                            @if($article->published_at)
                                <div class="text-gray-500 text-xs mb-2">{{ $article->published_at->format('Y-m-d') }}</div>
                            @endif
                            <h3 class="text-base font-bold text-white group-hover:text-gold transition-colors line-clamp-2 leading-snug mb-2">
                                {{ $relTrans?->title ?? $article->slug }}
                            </h3>
                            <span class="text-gold text-xs font-semibold">{{ __('messages.news_read_more') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</div>

@endsection

@push('scripts')
<script>
    const newsLightbox = GLightbox({ selector: '.glightbox' });

    const showAllBtn = document.getElementById('news-gallery-show-all');
    if (showAllBtn) {
        showAllBtn.addEventListener('click', function () {
            document.querySelectorAll('.glightbox .bg-dark\\/70').forEach(el => el.remove());
            document.querySelectorAll('.glightbox.hidden').forEach(el => el.classList.remove('hidden'));
            showAllBtn.style.display = 'none';
            newsLightbox.destroy();
            window._newsLb = GLightbox({ selector: '.glightbox' });
        });
    }
</script>
@endpush
