<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A tournament the site pulls from Tournated by itself (`tournated:sync`,
 * run every minute by the host's CRON) — replaces the PC-side push scripts.
 */
class TournatedSync extends Model
{
    protected $fillable = [
        'tournament_external_id',
        'enabled',
        'last_attempt_at',
        'last_success_at',
        'last_source',
        'match_count',
        'last_error',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_attempt_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];
}
