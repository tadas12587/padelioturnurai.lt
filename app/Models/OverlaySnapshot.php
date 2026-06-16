<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OverlaySnapshot extends Model
{
    protected $fillable = [
        'tournament_external_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
