<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phone running the "Padelio turnyrai" app (Expo push token). */
class AppDevice extends Model
{
    protected $fillable = ['expo_token', 'platform', 'tournament_external_id', 'role', 'me', 'me_key', 'follows', 'follow_keys'];

    protected $casts = ['follows' => 'array', 'follow_keys' => 'array'];

    /** @param string[] $people folded names of everyone in a match */
    public function interestIn(array $people): ?string
    {
        if ($this->role === 'player') {
            return $this->me_key && in_array($this->me_key, $people, true) ? 'me' : null;
        }

        return array_intersect($this->follow_keys ?? [], $people) ? 'follow' : null;
    }

    /** Display names of the followed players who are in the match. */
    public function followedIn(array $people): array
    {
        $out = [];
        foreach ($this->follow_keys ?? [] as $i => $key) {
            if (in_array($key, $people, true)) {
                $out[] = $this->follows[$i] ?? $key;
            }
        }

        return $out;
    }
}
