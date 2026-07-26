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

    /** @return array<string,mixed> current score state for the window (or []) */
    public static function stateFor(?string $tid, ?string $windowId = null): array
    {
        if ($tid === null || $tid === '') {
            return [];
        }

        $row = static::query()->where('tournament_external_id', $tid)->where('window_id', $windowId)->first();
        if ($row) {
            return $row->state ?? [];
        }

        // Legacy row from before scores were scoped per window — only as a
        // one-time read fallback so an in-progress match doesn't just vanish.
        return static::query()->where('tournament_external_id', $tid)->whereNull('window_id')->value('state') ?? [];
    }

    public static function matchFor(?string $tid, ?string $windowId = null): ?string
    {
        if ($tid === null || $tid === '') {
            return null;
        }

        $row = static::query()->where('tournament_external_id', $tid)->where('window_id', $windowId)->first();
        if ($row) {
            return $row->match_id === null ? null : (string) $row->match_id;
        }

        $v = static::query()->where('tournament_external_id', $tid)->whereNull('window_id')->value('match_id');

        return $v === null ? null : (string) $v;
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
