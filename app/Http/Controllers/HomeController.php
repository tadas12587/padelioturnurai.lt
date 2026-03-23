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

        $heroDir = rtrim(env('PUBLIC_STORAGE_PATH', public_path('storage')), '/') . '/herofoto';
        $heroPhotos = collect(glob($heroDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE))
            ->map(fn($p) => '/storage/herofoto/' . basename($p))
            ->shuffle()
            ->values();

        $tournaments = Tournament::with(['translations'])
            ->orderByRaw("FIELD(status, 'active', 'upcoming', 'past')")
            ->orderBy('date_start', 'desc')
            ->limit(6)
            ->get();

        return view('pages.home', compact('stats', 'globalSponsors', 'goldSponsors', 'tournaments', 'heroPhotos'));
    }
}
