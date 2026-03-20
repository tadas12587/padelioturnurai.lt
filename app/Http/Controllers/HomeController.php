<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use App\Models\Stat;
use App\Models\Tournament;

class HomeController extends Controller
{
    public function index()
    {
        $stats = Stat::all();
        $globalSponsors = Sponsor::whereNull('tournament_id')->where('is_active', true)->orderBy('sort_order')->get();
        $featuredTournament = Tournament::where('status', 'active')
            ->orWhere('status', 'upcoming')
            ->orderBy('date_start', 'desc')
            ->first() ?? Tournament::where('status', 'past')->orderBy('date_start', 'desc')->first();

        return view('pages.home', compact('stats', 'globalSponsors', 'featuredTournament'));
    }
}
