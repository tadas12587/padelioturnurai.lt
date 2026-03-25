<?php

namespace App\Http\Controllers;

use App\Models\News;

class NewsController extends Controller
{
    public function index()
    {
        $featured = News::with(['translations', 'tournament.translations'])
            ->published()
            ->where('is_featured', true)
            ->latest('published_at')
            ->first();

        $news = News::with(['translations', 'tournament.translations'])
            ->published()
            ->when($featured, fn ($q) => $q->where('id', '!=', $featured->id))
            ->latest('published_at')
            ->paginate(9);

        return view('pages.news', compact('featured', 'news'));
    }

    public function show(string $slug = '')
    {
        // Use named route parameter directly to avoid positional injection
        // issues when the route has a locale prefix parameter ({locale}/{slug})
        $slug = request()->route('slug', $slug);

        $news = News::with(['translations', 'tournament.translations'])
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $related = News::with(['translations'])
            ->published()
            ->where('id', '!=', $news->id)
            ->when($news->tournament_id, fn ($q) => $q->where('tournament_id', $news->tournament_id))
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('pages.news-show', compact('news', 'related'));
    }
}
