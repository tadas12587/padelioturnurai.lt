<?php

namespace App\Http\Controllers;

use App\Models\Tournament;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = Tournament::with(['translations'])
            ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'upcoming' THEN 2 WHEN 'past' THEN 3 END")
            ->orderBy('date_start', 'desc')
            ->get();

        return view('pages.tournaments', compact('tournaments'));
    }

    public function show(string $slug = '')
    {
        // Use named route parameter directly to avoid positional injection
        // issues when the route has a locale prefix parameter ({locale}/{slug})
        $slug = request()->route('slug', $slug);

        $tournament = Tournament::with(['translations', 'photos', 'sponsors' => function ($q) {
            $q->where('is_active', true)->orderBy('sort_order');
        }])->where('slug', $slug)->firstOrFail();

        return view('pages.tournament-show', compact('tournament'));
    }
}
