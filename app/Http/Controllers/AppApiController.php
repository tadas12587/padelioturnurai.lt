<?php

namespace App\Http\Controllers;

use App\Models\AppDevice;
use App\Models\PushSubscription;
use App\Models\ScheduleSnapshot;
use App\Models\Tournament;
use App\Services\Push\ExpoPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * JSON API for the "Padelio turnyrai" app (Expo). The schedule itself is the
 * same payload the /grafikas page polls (ScheduleController::data).
 */
class AppApiController extends Controller
{
    /** Our tournaments that have a public schedule: running and upcoming first. */
    public function tournaments(): JsonResponse
    {
        $snapshots = ScheduleSnapshot::pluck('payload', 'tournament_external_id');
        $today = now('Europe/Vilnius')->toDateString();

        $list = Tournament::with('translations')
            ->whereNotNull('tournated_id')->where('show_schedule', true)
            ->get()
            ->filter(fn (Tournament $t) => isset($snapshots[$t->tournated_id]))
            ->map(function (Tournament $t) use ($snapshots, $today) {
                $end = ($t->date_end ?? $t->date_start)?->toDateString();

                return [
                    'id' => (string) $t->tournated_id,
                    'name' => $t->translation('lt')?->title ?? ($snapshots[$t->tournated_id]['tournament']['name'] ?? $t->slug),
                    'dateStart' => $t->date_start?->toDateString(),
                    'dateEnd' => $end,
                    'location' => $t->location,
                    'phase' => $end && $end < $today ? 'past' : ($t->date_start?->toDateString() > $today ? 'upcoming' : 'live'),
                    'pushEnabled' => (bool) $t->push_enabled,
                ];
            })
            ->sortBy(fn ($t) => [['live' => 0, 'upcoming' => 1, 'past' => 2][$t['phase']], $t['phase'] === 'past' ? -strtotime($t['dateStart']) : strtotime($t['dateStart'])])
            ->values();

        return response()->json(['tournaments' => $list]);
    }

    /**
     * App home (v1.1+): every tournament we organise, like the website list —
     * running first, then upcoming (soonest first), then past (latest first).
     * `scheduleId` is set only when the app can open the live schedule for it.
     */
    public function home(): JsonResponse
    {
        $snapshots = ScheduleSnapshot::pluck('tournament_external_id')->flip();
        $today = now('Europe/Vilnius')->toDateString();

        $list = Tournament::with(['translations'])->withCount('photos')->get()
            ->map(function (Tournament $t) use ($snapshots, $today) {
                $start = $t->date_start?->toDateString();
                $end = ($t->date_end ?? $t->date_start)?->toDateString();
                $phase = $end && $end < $today ? 'past' : ($start && $start > $today ? 'upcoming' : 'live');
                $tr = $t->translation('lt');
                $hasSchedule = $t->tournated_id && $t->show_schedule && isset($snapshots[$t->tournated_id]);

                return [
                    'slug' => $t->slug,
                    'name' => $tr?->title ?? $t->slug,
                    'description' => $tr?->description ? trim(strip_tags($tr->description)) : null,
                    'dateStart' => $start,
                    'dateEnd' => $end,
                    'location' => $t->location,
                    'phase' => $phase,
                    'cover' => $t->cover_image ? url(Storage::url($t->cover_image)) : null,
                    'participantsCount' => (int) $t->participants_count,
                    'registrationActive' => (bool) ($t->registration_active && $t->registration_url && $phase === 'upcoming'),
                    'registrationUrl' => $t->registration_url,
                    'resultsText' => $tr?->results_text ?: ($t->results_text ?: null),
                    'resultsUrl' => $t->results_link ?: ($t->results_url ?: null),
                    'galleryUrl' => $t->gallery_url,
                    'photosCount' => (int) $t->photos_count,
                    'url' => route('tournament.show', $t->slug),
                    'scheduleId' => $hasSchedule ? (string) $t->tournated_id : null,
                    'pushEnabled' => (bool) $t->push_enabled,
                ];
            })
            ->sortBy(fn ($t) => [['live' => 0, 'upcoming' => 1, 'past' => 2][$t['phase']], $t['phase'] === 'past' ? -strtotime((string) $t['dateStart']) : strtotime((string) $t['dateStart'])])
            ->values();

        return response()->json(['tournaments' => $list]);
    }

    /** Register or update this phone: tournament, role and who it follows. */
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:200',
            'platform' => 'nullable|in:ios,android',
            'tournament' => 'required|string|max:20',
            'role' => 'required|in:player,spectator',
            'me' => 'nullable|required_if:role,player|string|max:120',
            'follows' => 'array|max:30',
            'follows.*' => 'string|max:120',
        ]);
        abort_unless(ExpoPush::isToken($data['token']), 422, 'Netinkamas pranešimų žetonas');
        abort_unless(self::acceptsPush($data['tournament']), 404);

        $follows = array_values(array_unique($data['follows'] ?? []));
        $device = AppDevice::updateOrCreate(['expo_token' => $data['token']], [
            'platform' => $data['platform'] ?? null,
            'tournament_external_id' => $data['tournament'],
            'role' => $data['role'],
            'me' => $data['role'] === 'player' ? $data['me'] : null,
            'me_key' => $data['role'] === 'player' ? PushSubscription::key($data['me']) : null,
            'follows' => $follows,
            'follow_keys' => array_map([PushSubscription::class, 'key'], $follows),
        ]);
        $device->touch(); // each app launch keeps the record alive (60-day prune)

        return response()->json(['ok' => true]);
    }

    /** Synced, not hidden by the organizer, notifications not switched off. */
    public static function acceptsPush(string $tournamentId): bool
    {
        if (! ScheduleSnapshot::where('tournament_external_id', $tournamentId)->exists()) {
            return false;
        }
        $site = Tournament::where('tournated_id', $tournamentId)->first();

        return ! $site || ($site->show_schedule && $site->push_enabled);
    }

    public function deleteDevice(Request $request): JsonResponse
    {
        AppDevice::where('expo_token', (string) $request->input('token'))->delete();

        return response()->json(['ok' => true]);
    }
}
