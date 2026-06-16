<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Overlay extends Model
{
    protected $fillable = [
        'name', 'type', 'token', 'tournament_external_id',
        'config', 'state', 'bracket_data',
    ];

    protected $casts = [
        'config'       => 'array',
        'state'        => 'array',
        'bracket_data' => 'array',
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
            $overlay->config ??= self::defaultConfig();
            $overlay->state  ??= self::defaultState();
        });
    }

    public static function defaultConfig(): array
    {
        return [
            'title'           => '',
            'accent_color'    => '#C9A84C',
            'logo'            => null,
            'position'        => 'bottom-left',
            'visible_columns' => ['place', 'name', 'wins', 'losses'],
        ];
    }

    public static function defaultState(): array
    {
        return [
            'active_category_id' => null,
            'active_group_id'    => null,
            'visible'            => false,
            'next_match'         => '',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
