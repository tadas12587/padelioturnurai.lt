<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationInterest extends Model
{
    protected $fillable = ['tournament_id', 'name', 'email', 'locale'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
