<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class News extends Model
{
    protected $fillable = [
        'slug',
        'status',
        'published_at',
        'tournament_id',
        'cover_image',
        'is_featured',
        'buttons',
        'photo_paths',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured'  => 'boolean',
            'buttons'      => 'array',
            'photo_paths'  => 'array',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(NewsTranslation::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function translation(?string $locale = null): ?NewsTranslation
    {
        $locale = $locale ?? app()->getLocale();

        $translation = $this->translations->firstWhere('locale', $locale);

        if ($translation === null && $locale !== 'lt') {
            $translation = $this->translations->firstWhere('locale', 'lt');
        }

        return $translation;
    }

    public function readingTime(?string $locale = null): int
    {
        $words = str_word_count(strip_tags($this->translation($locale)?->content ?? ''));

        return max(1, (int) ceil($words / 200));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
