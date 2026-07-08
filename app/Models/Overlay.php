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

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    // ── Active windows (multiple can be shown at once) ──────────────
    // Stored as state['active_window_ids'] (list). Legacy single
    // state['active_window_id'] is still honoured for old records.

    /** @return list<string> */
    public static function activeIds(array $state): array
    {
        if (array_key_exists('active_window_ids', $state) && is_array($state['active_window_ids'])) {
            return array_values(array_filter(array_map('strval', $state['active_window_ids'])));
        }
        $single = $state['active_window_id'] ?? null;

        return $single ? [(string) $single] : [];
    }

    public static function isShown(array $state, string $id): bool
    {
        return in_array($id, static::activeIds($state), true);
    }

    /** @param  list<string>  $ids */
    public static function withActive(array $state, array $ids): array
    {
        $state['active_window_ids'] = array_values(array_filter(array_map('strval', $ids)));
        unset($state['active_window_id']); // migrated to the list form

        return $state;
    }

    public static function showWindow(array $state, string $id): array
    {
        $ids = static::activeIds($state);
        if (! in_array($id, $ids, true)) {
            $ids[] = $id;
        }

        return static::withActive($state, $ids);
    }

    public static function hideWindow(array $state, string $id): array
    {
        return static::withActive($state, array_values(array_filter(
            static::activeIds($state),
            fn ($x) => $x !== $id,
        )));
    }

    public static function hideAll(array $state): array
    {
        return static::withActive($state, []);
    }
}
