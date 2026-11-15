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
        'tv_courts',
        'tv_screens',
        'tv_show_place',
        'tv_screen_inch',
        'tv_distance_m',
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
            'tv_show_place'       => 'boolean',
            'tv_screens'          => 'array',
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

    /** The TV screens set in admin, each with a name and its Tournated court IDs. */
    public function tvScreens(): array
    {
        return array_values(array_filter(array_map(fn ($s) => [
            'name' => trim((string) ($s['name'] ?? '')),
            'courts' => array_values(array_map('intval', (array) ($s['courts'] ?? []))),
        ], (array) $this->tv_screens), fn ($s) => $s['courts'] !== []));
    }

    /** "1, 3,4" -> [1, 3, 4]; empty = every court of the day. */
    public function tvCourtNumbers(?string $override = null): array
    {
        preg_match_all('/\d+/', (string) ($override ?? $this->tv_courts), $m);

        return array_values(array_unique(array_map('intval', $m[0])));
    }

    /**
     * Readable text for a TV: ADA 2010 table 703.5.5 — capital letters 5/8" high
     * up to 6 ft away, plus 1/8" per extra foot. Returns the font size as a
     * share of the screen height (cap height ≈ 0.72 em for Inter / Archivo).
     */
    public static function tvFontShare(int $screenInch, float $distanceM): float
    {
        $capMm = 15.9 + max(0, $distanceM * 1000 - 1829) / 304.8 * 3.175;
        $screenHeightMm = $screenInch * 25.4 * 9 / sqrt(16 ** 2 + 9 ** 2);

        // ×0.8: the strict sign standard looked too big on a real screen (user, 2026-11-15) — a middle ground
        return 0.8 * ($capMm / 0.72) / $screenHeightMm;
    }

    /** How many court columns fit with ~16 characters per name line at that size. */
    public static function tvMaxCourts(int $screenInch, float $distanceM): int
    {
        $fontShareOfWidth = self::tvFontShare($screenInch, $distanceM) * 9 / 16;

        return max(1, (int) floor(1 / ($fontShareOfWidth * 0.56 * 16 * 1.12)));
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
