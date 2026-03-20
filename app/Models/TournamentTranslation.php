<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tournament_id',
        'locale',
        'title',
        'description',
        'results_text',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
