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

        $globalSponsors = Sponsor::where('is_general', true)->where('is_active', true)->orderBy('sort_order')->get();
        $goldSponsors = $globalSponsors->where('category', 'gold');

        $tournaments = Tournament::with(['translations'])
            ->orderByRaw("FIELD(status, 'active', 'upcoming', 'past')")
            ->orderBy('date_start', 'desc')
            ->limit(6)
            ->get();

        return view('pages.home', compact('stats', 'globalSponsors', 'goldSponsors', 'tournaments'));
    }
}
