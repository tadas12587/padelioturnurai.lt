<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Manually imported entry list for a tournament: pairs per category, so the
 * draw can run before Tournated matches exist. `data` is keyed by a normalised
 * category name → list of teams [{id, name, seed, gender, country}].
 */
class EntryList extends Model
{
    protected $fillable = ['tournament_external_id', 'data', 'source_name'];

    protected $casts = ['data' => 'array'];

    /** Normalise a category name for matching (trim, collapse spaces, lowercase). */
    public static function normCategory(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }
}
