<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tournament extends Model
{
    protected $fillable = [
        'slug',
        'tournated_id',
        'sync_enabled',
        'show_schedule',
        'push_enabled',
        'participants_auto',
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
            'sync_enabled'        => 'boolean',
            'show_schedule'       => 'boolean',
            'push_enabled'        => 'boolean',
            'participants_auto'   => 'boolean',
        ];
    }

    /**
     * A Tournated ID turns on the site's own sync (tournated:sync) for it;
     * removing or changing the ID turns the old one off.
     */
    protected static function booted(): void
    {
        static::saved(function (Tournament $t) {
            $old = $t->getOriginal('tournated_id');
            if ($t->wasChanged('tournated_id') && $old && $old !== $t->tournated_id) {
                TournatedSync::where('tournament_external_id', $old)->update(['enabled' => false]);
            }
            if ($t->tournated_id) {
                TournatedSync::updateOrCreate(['tournament_external_id' => $t->tournated_id], ['enabled' => $t->sync_enabled ?? true]);
            }
        });
        static::deleted(function (Tournament $t) {
            if ($t->tournated_id) {
                TournatedSync::where('tournament_external_id', $t->tournated_id)->update(['enabled' => false]);
            }
        });
    }

    public function tournatedSync(): HasOne
    {
        return $this->hasOne(TournatedSync::class, 'tournament_external_id', 'tournated_id');
    }

    /** "11532", "#11532" or "https://play.padel.lt/tournament/11532/..." -> "11532" */
    public static function parseTournatedId(?string $input): ?string
    {
        if (! $input) {
            return null;
        }
        if (preg_match('~/tournament/(\d+)~', $input, $m) || preg_match('~^\s*#?(\d{3,9})\s*$~', $input, $m)) {
            return $m[1];
        }

        return null;
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
