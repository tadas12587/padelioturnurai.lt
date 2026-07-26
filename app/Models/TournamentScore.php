<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The live scoreboard for one score window. Scoped by (tournament_external_id,
 * window_id) so two independent score windows — even of the same tournament —
 * never collide. Windows sharing state (e.g. several boards for one match)
 * simply point at the same window_id.
 */
class TournamentScore extends Model
{
    protected $fillable = ['tournament_external_id', 'window_id', 'match_id', 'state'];

    protected $casts = ['state' => 'array'];

    /** One query for the window-scoped row, falling back to the legacy
     *  (pre-window-scoping) row only if nothing window-scoped exists yet. */
    private static function rowFor(?string $tid, ?string $windowId): ?self
    {
        if ($tid === null || $tid === '') {
            return null;
        }

        return static::query()->where('tournament_external_id', $tid)->where('window_id', $windowId)->first()
            ?? static::query()->where('tournament_external_id', $tid)->whereNull('window_id')->first();
    }

    /** @return array<string,mixed> current score state for the window (or []) */
    public static function stateFor(?string $tid, ?string $windowId = null): array
    {
        return static::rowFor($tid, $windowId)?->state ?? [];
    }

    public static function matchFor(?string $tid, ?string $windowId = null): ?string
    {
        $v = static::rowFor($tid, $windowId)?->match_id;

        return $v === null ? null : (string) $v;
    }

    /**
     * State + match_id in a single query — for call sites that need both (a
     * point/game update reads the current state AND keeps the current match),
     * so they don't fetch the same row twice.
     *
     * @return array{state:array<string,mixed>,match_id:?string}
     */
    public static function bothFor(?string $tid, ?string $windowId = null): array
    {
        $row = static::rowFor($tid, $windowId);

        return [
            'state'    => $row?->state ?? [],
            'match_id' => $row?->match_id === null ? null : (string) $row->match_id,
        ];
    }

    /** Persist the score for one window + which match it belongs to. */
    public static function put(string $tid, array $state, $matchId = null, ?string $windowId = null): void
    {
        static::updateOrCreate(
            ['tournament_external_id' => $tid, 'window_id' => $windowId],
            ['state' => $state, 'match_id' => $matchId === null ? null : (string) $matchId],
        );
    }
}
