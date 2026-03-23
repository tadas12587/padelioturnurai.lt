<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sponsor extends Model
{
    protected $fillable = [
        'name',
        'logo',
        'url',
        'category',
        'is_general',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_general' => 'boolean',
    ];

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class);
    }

    public function scopeGeneral($query)
    {
        return $query->where('is_general', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
