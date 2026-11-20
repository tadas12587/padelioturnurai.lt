<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use App\Models\Stat;
use App\Models\Tournament;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        $stats = Stat::all();

        $globalSponsors = Sponsor::where('is_general', true)->where('is_active', true)->orderBy('sort_order')->get();

        $heroDir = rtrim(env('PUBLIC_STORAGE_PATH', public_path('storage')), '/') . '/herofoto';
        $heroPhotos = collect(glob($heroDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE))
            ->map(fn($p) => '/storage/herofoto/' . basename($p))
            ->shuffle()
            ->values();

        $today = Carbon::now('Europe/Vilnius')->startOfDay();

        // The next tournament that hasn't finished yet (today counts), soonest first.
        $nextTournament = Tournament::with(['translations'])
            ->whereIn('status', ['active', 'upcoming'])
            ->where(fn($q) => $q->whereDate('date_end', '>=', $today)
                ->orWhere(fn($q) => $q->whereNull('date_end')->whereDate('date_start', '>=', $today)))
            ->orderBy('date_start')
            ->first();

        $isLive = false;
        if ($nextTournament) {
            $start = $nextTournament->date_start->copy()->startOfDay();
            $end = ($nextTournament->date_end ?? $nextTournament->date_start)->copy()->startOfDay();
            $isLive = $today->betweenIncluded($start, $end);
        }

        $pastTournaments = Tournament::with(['translations'])
            ->when($nextTournament, fn($q) => $q->where('id', '!=', $nextTournament->id))
            ->whereDate('date_start', '<', $today)
            ->orderBy('date_start', 'desc')
            ->limit(4)
            ->get();

        return view('pages.home', compact('stats', 'globalSponsors', 'nextTournament', 'isLive', 'pastTournaments', 'heroPhotos'));
    }
}
