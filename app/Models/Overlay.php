<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Overlay extends Model
{
    protected $fillable = [
        'name', 'type', 'token', 'tournament_external_id',
        'config', 'state', 'bracket_data', 'windows',
    ];

    protected $casts = [
        'config'       => 'array',
        'state'        => 'array',
        'bracket_data' => 'array',
        'windows'      => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Overlay $overlay) {
            if (empty($overlay->token)) {
                do {
                    $token = Str::lower(Str::random(8));
                } while (static::where('token', $token)->exists());
                $overlay->token = $token;
            }
            $overlay->type    ??= 'group_standings'; // legacy column, kept NOT NULL
            $overlay->config  ??= self::defaultConfig();
            $overlay->state   ??= self::defaultState();
            $overlay->windows ??= [];
        });
    }

    public static function defaultConfig(): array
    {
        return [
            'title'           => '',
            'theme'           => 'gold_night',
            'colors'          => self::themePresets()['gold_night']['colors'],
            'logo'            => null,
            'position'        => 'bottom-left',
            'visible_columns' => ['place', 'name', 'points', 'wins', 'losses'],
        ];
    }

    public static function defaultState(): array
    {
        return [
            'active_window_id' => null,
            'next_match'       => '',
        ];
    }

    /** @return array<string,array{label:string,colors:array<string,string>}> */
    public static function themePresets(): array
    {
        return [
            'gold_night'  => ['label' => 'Auksinė naktis', 'colors' => ['bg' => '#111118', 'text' => '#F5F5F0', 'accent' => '#C9A84C', 'muted' => '#9CA3AF']],
            'light'       => ['label' => 'Šviesi',         'colors' => ['bg' => '#FFFFFF', 'text' => '#111118', 'accent' => '#C9A84C', 'muted' => '#6B7280']],
            'court_blue'  => ['label' => 'Mėlyna (kortas)','colors' => ['bg' => '#0B1E3B', 'text' => '#F5F8FF', 'accent' => '#4FA3FF', 'muted' => '#7E93B8']],
            'court_green' => ['label' => 'Žalia (kortas)', 'colors' => ['bg' => '#0C2A1F', 'text' => '#F2FBF6', 'accent' => '#34D399', 'muted' => '#79A893']],
            'red_black'   => ['label' => 'Raudona/juoda',  'colors' => ['bg' => '#1A0D0D', 'text' => '#FBEDED', 'accent' => '#EF4444', 'muted' => '#A98686']],
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
