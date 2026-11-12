<?php

namespace App\Services\Tournated;

/**
 * Converts official API rows into the internal schedule shapes the public
 * /grafikas page and overlays read. Personal data (e-mail, phone, birth date)
 * is never copied — only names.
 */
class MatchShape
{
    public static function fromApi(array $m): array
    {
        $sets = [];
        foreach ($m['sets'] ?? [] as $s) {
            if (($s['side1'] ?? null) !== null && ($s['side2'] ?? null) !== null) {
                $sets[] = ['side1' => (int) $s['side1'], 'side2' => (int) $s['side2']];
            }
        }

        return [
            'match_id' => $m['match_id'],
            'date' => $m['date'] ?? null,
            'time' => $m['time'] ?? null,
            'court' => isset($m['court']['court_id']) ? ['court_id' => $m['court']['court_id'], 'name' => $m['court']['name'] ?? null] : null,
            'division' => $m['category']['name'] ?? null,
            'team1' => self::team($m['team1'] ?? null),
            'team2' => self::team($m['team2'] ?? null),
            'participants' => array_map(fn ($p) => self::person($p['side'] ?? null, $p['name'] ?? '', $p['surname'] ?? ''), $m['participants'] ?? []),
            'sets' => $sets,
            'winner_side' => $m['winner_side'] ?? self::winnerFromSets($sets),
            'is_bye' => (bool) ($m['is_bye'] ?? false),
            'is_walkover' => (bool) ($m['is_walkover'] ?? false),
            'is_disqualified' => (bool) ($m['is_disqualified'] ?? false),
            'is_match_tie' => (bool) ($m['is_match_tie'] ?? false),
            'is_match_in_progress' => (bool) ($m['is_match_in_progress'] ?? false),
            'round' => $m['round'] ?? null,
            'match_for' => $m['match_for'] ?? null,
            'bracket_type' => $m['bracket_type'] ?? null,
            'updated_at' => $m['updated_at'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Some Tournated profiles store the full name in `name` as well
     * ("Simona Diržinskaitė" + "Diržinskaitė") — drop the repeated surname.
     */
    public static function person(?int $side, ?string $name, ?string $surname): array
    {
        $name = trim((string) $name);
        $surname = trim((string) $surname);
        if ($surname !== '' && mb_strtolower($name) !== mb_strtolower($surname)
            && str_ends_with(mb_strtolower($name), ' '.mb_strtolower($surname))) {
            $name = trim(mb_substr($name, 0, mb_strlen($name) - mb_strlen($surname)));
        }

        return ['side' => $side, 'name' => $name, 'surname' => $surname];
    }

    public static function winnerFromSets(array $sets): ?int
    {
        $w1 = $w2 = 0;
        foreach ($sets as $s) {
            if ($s['side1'] > $s['side2']) {
                $w1++;
            } elseif ($s['side2'] > $s['side1']) {
                $w2++;
            }
        }

        return $w1 === $w2 ? null : ($w1 > $w2 ? 1 : 2);
    }

    /** Group / draw / standings entry: keep position + names only. */
    public static function entry(array $e): array
    {
        return array_filter([
            'position' => $e['position'] ?? null,
            'seed' => $e['seed'] ?? null,
            'points' => $e['points'] ?? null,
            'place_label' => $e['place_label'] ?? null,
            'team_id' => $e['team_id'] ?? null,
            'team_name' => $e['team_name'] ?? null,
            'player' => isset($e['player']) ? [
                'player_id' => $e['player']['player_id'] ?? null,
                'name' => trim((string) ($e['player']['name'] ?? '')),
                'surname' => trim((string) ($e['player']['surname'] ?? '')),
            ] : null,
        ], fn ($v) => $v !== null);
    }

    private static function team(?array $t): ?array
    {
        if (! $t) {
            return null;
        }

        return [
            'team_id' => $t['team_id'] ?? $t['id'] ?? null,
            'title' => $t['title'] ?? $t['name'] ?? $t['team_name'] ?? null,
            'image' => $t['image'] ?? null,
        ];
    }
}
