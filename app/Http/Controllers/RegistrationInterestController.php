<?php

namespace App\Http\Controllers;

use App\Models\RegistrationInterest;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationInterestController extends Controller
{
    /**
     * Store a new registration interest (JSON endpoint).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tournament_id' => 'nullable|exists:tournaments,id',
            'name'          => 'required|string|max:120',
            'email'         => 'required|email|max:150',
        ]);

        $alreadyExists = RegistrationInterest::where('tournament_id', $data['tournament_id'] ?? null)
            ->where('email', $data['email'])
            ->exists();

        if ($alreadyExists) {
            return response()->json(['success' => true, 'status' => 'already']);
        }

        RegistrationInterest::create([
            'tournament_id' => $data['tournament_id'] ?? null,
            'name'          => $data['name'],
            'email'         => $data['email'],
            'locale'        => app()->getLocale(),
        ]);

        return response()->json(['success' => true, 'status' => 'saved']);
    }

    /**
     * Export all interests as CSV (admin only, protected by auth middleware).
     */
    public function export(): StreamedResponse
    {
        $interests = RegistrationInterest::with(['tournament.translations'])
            ->orderBy('tournament_id')
            ->orderBy('created_at')
            ->get();

        $filename = 'registracijos-domejimasis-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($interests) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens correctly
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Turnyras', 'Vardas', 'El. paštas', 'Kalba', 'Data']);

            foreach ($interests as $interest) {
                $trans = $interest->tournament?->translations->firstWhere('locale', 'lt')
                    ?? $interest->tournament?->translations->first();
                $tournamentName = $trans?->title ?? $interest->tournament?->slug ?? '—';

                fputcsv($handle, [
                    $tournamentName,
                    $interest->name,
                    $interest->email,
                    $interest->locale,
                    $interest->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
