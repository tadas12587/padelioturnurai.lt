<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'slug',
        'status',
        'date_start',
        'date_end',
        'location',
        'participants_count',
        'registration_url',
        'registration_active',
        'results_url',
        'results_text',
        'results_link',
        'gallery_url',
        'cover_image',
        'notify_enabled',
    ];

    protected function casts(): array
    {
        return [
            'date_start'          => 'date',
            'date_end'            => 'date',
            'registration_active' => 'boolean',
            'notify_enabled'      => 'boolean',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TournamentTranslation::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TournamentPhoto::class);
    }

    public function sponsors(): BelongsToMany
    {
        return $this->belongsToMany(Sponsor::class);
    }

    public function translation(?string $locale = null): ?TournamentTranslation
    {
        $locale = $locale ?? app()->getLocale();

        $translation = $this->translations->firstWhere('locale', $locale);

        if ($translation === null && $locale !== 'lt') {
            $translation = $this->translations->firstWhere('locale', 'lt');
        }

        return $translation;
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
