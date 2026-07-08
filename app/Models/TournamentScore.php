<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The live scoreboard shared across all overlays of one tournament.
 * The state is the same shape ScoreEngine produces; match_id is the fixture
 * the score belongs to. Enter a result once → visible in every overlay.
 */
class TournamentScore extends Model
{
    protected $fillable = ['tournament_external_id', 'match_id', 'state'];

    protected $casts = ['state' => 'array'];

    /** @return array<string,mixed> current score state for the tournament (or []) */
    public static function stateFor(?string $tid): array
    {
        if ($tid === null || $tid === '') {
            return [];
        }

        return static::query()->where('tournament_external_id', $tid)->value('state') ?? [];
    }

    public static function matchFor(?string $tid): ?string
    {
        if ($tid === null || $tid === '') {
            return null;
        }
        $v = static::query()->where('tournament_external_id', $tid)->value('match_id');

        return $v === null ? null : (string) $v;
    }

    /** Persist the shared score + which match it belongs to. */
    public static function put(string $tid, array $state, $matchId = null): void
    {
        static::updateOrCreate(
            ['tournament_external_id' => $tid],
            ['state' => $state, 'match_id' => $matchId === null ? null : (string) $matchId],
        );
    }
}
