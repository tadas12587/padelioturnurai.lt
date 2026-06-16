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
            'gold_night'  => ['label' => 'Auksinė naktis',       'colors' => ['bg' => '#111118', 'text' => '#F5F5F0', 'accent' => '#C9A84C', 'muted' => '#9CA3AF']],
            'light'       => ['label' => 'Šviesi',               'colors' => ['bg' => '#FFFFFF', 'text' => '#111118', 'accent' => '#C9A84C', 'muted' => '#6B7280']],
            'court_blue'  => ['label' => 'Mėlyna (kortas)',      'colors' => ['bg' => '#0B1E3B', 'text' => '#F5F8FF', 'accent' => '#4FA3FF', 'muted' => '#7E93B8']],
            'court_green' => ['label' => 'Žalia (kortas)',       'colors' => ['bg' => '#0C2A1F', 'text' => '#F2FBF6', 'accent' => '#34D399', 'muted' => '#79A893']],
            'red_black'   => ['label' => 'Raudona/juoda',        'colors' => ['bg' => '#1A0D0D', 'text' => '#FBEDED', 'accent' => '#EF4444', 'muted' => '#A98686']],
            'midnight'    => ['label' => 'Naktinė mėlyna',       'colors' => ['bg' => '#0A1A2F', 'text' => '#EAF2FF', 'accent' => '#38BDF8', 'muted' => '#6E8CB0']],
            'graphite'    => ['label' => 'Grafitas',             'colors' => ['bg' => '#17181B', 'text' => '#F4F4F5', 'accent' => '#D4D4D8', 'muted' => '#8A8D93']],
            'wine_gold'   => ['label' => 'Vynas ir auksas',      'colors' => ['bg' => '#2A0E16', 'text' => '#FBEEF1', 'accent' => '#D4AF37', 'muted' => '#A77E86']],
            'esports'     => ['label' => 'Elektrinė violetinė',  'colors' => ['bg' => '#150F2B', 'text' => '#F1ECFF', 'accent' => '#8B5CF6', 'muted' => '#8A7CB8']],
            'orange'      => ['label' => 'Oranžinė energija',    'colors' => ['bg' => '#14110D', 'text' => '#FFF3E6', 'accent' => '#FB923C', 'muted' => '#B0937A']],
            'ice'         => ['label' => 'Ledo mėlyna (šviesi)', 'colors' => ['bg' => '#F4F8FC', 'text' => '#0B2238', 'accent' => '#2563EB', 'muted' => '#5B7290']],
        ];
    }

    /**
     * Generate an empty single-elimination bracket (flat match list) for the
     * given draw size, including the 3rd-place match.
     *
     * @return list<array<string,mixed>>
     */
    public static function bracketSkeleton(int $size): array
    {
        $rounds = $size === 16
            ? [['1/8 finalio', 8], ['Ketvirtfinaliai', 4], ['Pusfinaliai', 2], ['Finalas', 1]]
            : [['Ketvirtfinaliai', 4], ['Pusfinaliai', 2], ['Finalas', 1]];

        $blank = fn (string $round) => [
            'round' => $round, 'team1' => '', 'team2' => '', 'sets1' => '', 'sets2' => '', 'winner' => null,
        ];

        $matches = [];
        foreach ($rounds as [$title, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $matches[] = $blank($title);
            }
        }
        $matches[] = $blank('Dėl 3 vietos');

        return $matches;
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
