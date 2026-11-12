<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Homepage numbers. In "auto" mode the value is computed from the
 * tournaments (participants filled from Tournated) plus base_value, which
 * covers history from before the tournaments were entered on the site.
 */
class Stat extends Model
{
    public const AUTO_KEYS = [
        'total_participants' => 'Dalyvių suma visuose įvykusiuose turnyruose',
        'total_tournaments' => 'Įvykusių turnyrų skaičius',
        'years_active' => 'Metai nuo pirmo turnyro iki dabar',
    ];

    protected $fillable = [
        'key',
        'value',
        'mode',
        'base_value',
        'label_lt',
        'label_en',
    ];

    protected $casts = ['base_value' => 'integer'];

    public static function getValue(string $key): int
    {
        $stat = static::where('key', $key)->first();

        return $stat ? $stat->display_value : 0;
    }

    /** The number the homepage shows. */
    public function getDisplayValueAttribute(): int
    {
        if ($this->mode === 'auto' && isset(self::AUTO_KEYS[$this->key])) {
            return self::computed($this->key) + (int) $this->base_value;
        }

        return (int) $this->value;
    }

    public static function computed(string $key): int
    {
        $held = Tournament::whereDate('date_start', '<=', now()->toDateString());

        return match ($key) {
            'total_participants' => (int) (clone $held)->sum('participants_count'),
            'total_tournaments' => (clone $held)->count(),
            'years_active' => ($first = (clone $held)->min('date_start')) ? now()->year - (int) substr($first, 0, 4) + 1 : 0,
            default => 0,
        };
    }
}
