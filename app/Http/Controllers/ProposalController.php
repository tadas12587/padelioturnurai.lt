<?php

namespace App\Http\Controllers;

use App\Models\ProposalTier;
use App\Models\Setting;
use App\Models\Tournament;

class ProposalController extends Controller
{
    public function index()
    {
        $tiers = ProposalTier::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $tournamentId = Setting::get('proposal_tournament_id');
        $tournament   = $tournamentId
            ? Tournament::with('translations')->find($tournamentId)
            : null;

        $photosJson = Setting::get('proposal_photos', '[]');
        $photos     = is_array($photosJson)
            ? $photosJson
            : (json_decode($photosJson, true) ?? []);

        $s = fn (string $key, string $default = '') => Setting::get($key, $default);

        return view('pages.sponsor-proposal', compact('tiers', 'tournament', 'photos', 's'));
    }
}
