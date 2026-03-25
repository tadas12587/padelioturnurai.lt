<?php

namespace App\Http\Controllers;

use App\Models\ProposalTier;
use App\Models\Setting;
use App\Models\Tournament;

class ProposalController extends Controller
{
    public function index()
    {
        $locale = app()->getLocale();

        // Locale-aware getter: returns _en variant when in English (if filled)
        $st = function (string $key, string $default = '') use ($locale): string {
            if ($locale === 'en') {
                $en = Setting::get($key . '_en', '');
                if ($en !== '') {
                    return $en;
                }
            }
            return Setting::get($key, $default);
        };

        // Plain getter (no locale logic, e.g. for stats values / URLs)
        $s = fn (string $key, string $default = '') => Setting::get($key, $default);

        $tiers = ProposalTier::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $tournamentId = $s('proposal_tournament_id');
        $tournament   = $tournamentId
            ? Tournament::with(['translations', 'sponsors' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])->find($tournamentId)
            : null;

        $currentSponsors = $tournament?->sponsors ?? collect();

        // General photos
        $photosJson = $s('proposal_photos', '[]');
        $photos     = is_array($photosJson)
            ? $photosJson
            : (json_decode($photosJson, true) ?? []);

        // Streaming section data
        $streamPhoto = $s('proposal_stream_photo');
        $streamStats = array_filter([
            ['value' => $s('proposal_stream_stat1_value'), 'label' => $st('proposal_stream_stat1_label', 'Transliacijos kanalų')],
            ['value' => $s('proposal_stream_stat2_value'), 'label' => $st('proposal_stream_stat2_label', 'Live žiūrovų')],
            ['value' => $s('proposal_stream_stat3_value'), 'label' => $st('proposal_stream_stat3_label', 'Peržiūrų vėliau')],
            ['value' => $s('proposal_stream_stat4_value'), 'label' => $st('proposal_stream_stat4_label', '')],
        ], fn ($row) => ! empty($row['value']));

        return view('pages.sponsor-proposal', compact(
            'tiers', 'tournament', 'photos',
            'streamPhoto', 'streamStats',
            'currentSponsors',
            's', 'st'
        ));
    }
}
