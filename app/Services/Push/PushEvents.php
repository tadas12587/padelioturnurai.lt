<?php

namespace App\Services\Push;

use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Services\Tournated\CourtDelays;
use Carbon\CarbonImmutable;

/**
 * Turns what changed between two syncs of a tournament into phone
 * notifications for the players involved (only today's matches):
 *
 *  - turn:  the previous match on the court got its result → "Tavo eilė! Eik į Kortą 4"
 *           (first match of the day on a court: 10 min before its planned time)
 *  - court: the organizer moved the match to another court
 *  - time:  planned time changed, or the estimate moved by more than 15 min
 *  - opp:   the pairs in the match changed
 *  - gone:  the match disappeared from the schedule
 *
 * Messages are queued (push_messages) and sent by PushDispatcher in the web
 * context. dedupe_key makes every event go out at most once per phone.
 */
class PushEvents
{
    public const ETA_SHIFT = 15;

    public const FIRST_MATCH_LEAD = 10;

    /** @return array matches with 'eta_announced' kept up to date */
    public static function detect(string $tournamentId, array $before, array $after, bool $comparable, string $tz, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone($tz);
        $today = $now->toDateString();
        $nowMin = $now->hour * 60 + $now->minute;
        $subs = PushSubscription::where('tournament_external_id', $tournamentId)->get();

        $prev = [];
        foreach ($before as $m) {
            $prev[$m['match_id']] = $m;
        }
        $etas = [];
        foreach (CourtDelays::apply($after, $now, $tz)['matches'] as $m) {
            $etas[$m['match_id']] = $m['eta'] ?? null;
        }

        $queue = [];
        $send = function (array $m, string $kind, string $key, string $title, string $body, ?array $people = null) use (&$queue) {
            $queue[] = compact('m', 'kind', 'key', 'title', 'body', 'people');
        };

        foreach ($after as $i => $m) {
            $old = $prev[$m['match_id']] ?? null;
            $eta = $etas[$m['match_id']] ?? ($m['time'] ?? null);
            $after[$i]['eta_announced'] = $old['eta_announced'] ?? $eta;

            if (($m['date'] ?? null) !== $today || self::played($m) || ! $old) {
                continue;
            }
            $court = self::court($m);
            $teams = self::teams($m);

            if (($old['court']['name'] ?? null) && ($m['court']['name'] ?? null) && $old['court']['name'] !== $m['court']['name']) {
                $send($m, 'court', "court:{$m['match_id']}:{$m['court']['name']}", "Pasikeitė kortas: dabar {$court}", "{$teams} · ~{$eta}");
            }
            if (($old['time'] ?? null) && ($m['time'] ?? null) && $old['time'] !== $m['time']) {
                $send($m, 'time', "time:{$m['match_id']}:{$m['time']}", "Pasikeitė mačo laikas: ~{$eta}", "{$court} · {$teams}");
                $after[$i]['eta_announced'] = $eta;
            } elseif ($eta && $after[$i]['eta_announced'] && abs(self::min($eta) - self::min($after[$i]['eta_announced'])) > self::ETA_SHIFT) {
                $send($m, 'time', "eta:{$m['match_id']}:{$eta}", "Tavo mačas dabar apie {$eta}", "{$court} · {$teams} (planuota {$m['time']})");
                $after[$i]['eta_announced'] = $eta;
            }
            if (self::signature($old) !== self::signature($m) && self::signature($old) !== '') {
                $sig = substr(md5(self::signature($m)), 0, 8);
                $send($m, 'opp', "opp:{$m['match_id']}:{$sig}", 'Pasikeitė mačo poros', "{$teams} · {$court} ~{$eta}",
                    array_unique(array_merge(self::people($old), self::people($m))));
            }
        }

        // "Your turn": the previous match on a court has just got its result.
        foreach ($after as $m) {
            $old = $prev[$m['match_id']] ?? null;
            if (($m['date'] ?? null) !== $today || ! self::played($m) || ! $old || self::played($old) || ! empty($m['result_batch'])) {
                continue;
            }
            if ($next = self::nextOnCourt($after, $m)) {
                $send($next, 'turn', "turn:{$next['match_id']}", 'Tavo eilė! Eik į '.self::court($next, true), self::teams($next).(! empty($next['division']) ? ' · '.$next['division'] : ''));
            }
        }
        // First match of the day on a court: nothing before it, so remind shortly before the planned time.
        foreach ($after as $m) {
            if (($m['date'] ?? null) !== $today || self::played($m) || empty($m['time']) || empty($m['court']['name'])) {
                continue;
            }
            $lead = self::min($m['time']) - $nowMin;
            if ($lead <= self::FIRST_MATCH_LEAD && $lead >= -20 && ! self::earlierOnCourt($after, $m)) {
                $send($m, 'turn', "turn:{$m['match_id']}", 'Tavo eilė! Eik į '.self::court($m, true), self::teams($m).' · '.$m['time']);
            }
        }
        // Removed from the schedule (only when both syncs came from the same source and lists are complete).
        if ($comparable) {
            $ids = array_flip(array_column($after, 'match_id'));
            $gone = array_filter($before, fn ($m) => ! isset($ids[$m['match_id']]) && ($m['date'] ?? null) === $today && ! self::played($m));
            if (count($gone) <= 3) {
                foreach ($gone as $m) {
                    $send($m, 'gone', "gone:{$m['match_id']}", 'Mačas išimtas iš grafiko', self::teams($m).' · buvo '.self::court($m).' '.($m['time'] ?? ''));
                }
            }
        }

        self::enqueue($tournamentId, $subs, $queue);

        return $after;
    }

    private static function enqueue(string $tournamentId, $subs, array $queue): void
    {
        if ($subs->isEmpty() || ! $queue) {
            return;
        }
        foreach ($queue as $q) {
            $people = $q['people'] ?? self::people($q['m']);
            foreach ($subs as $sub) {
                if (! in_array($sub->player_key, $people, true)) {
                    continue;
                }
                PushMessage::firstOrCreate(
                    ['push_subscription_id' => $sub->id, 'dedupe_key' => $q['key']],
                    [
                        'kind' => $q['kind'],
                        'title' => mb_substr($q['title'], 0, 190),
                        'body' => mb_substr($q['body'], 0, 490),
                        'url' => '/grafikas/'.rawurlencode($tournamentId).'?z='.rawurlencode($sub->player),
                    ],
                );
            }
        }
    }

    private static function nextOnCourt(array $matches, array $m): ?array
    {
        $cands = array_filter($matches, fn ($x) => ($x['date'] ?? null) === $m['date'] && ($x['court']['name'] ?? null) === ($m['court']['name'] ?? '#')
            && ! empty($x['time']) && ! self::played($x) && self::min($x['time']) > self::min($m['time'] ?? '00:00'));
        usort($cands, fn ($a, $b) => self::min($a['time']) <=> self::min($b['time']));

        return $cands[0] ?? null;
    }

    private static function earlierOnCourt(array $matches, array $m): bool
    {
        foreach ($matches as $x) {
            if (($x['date'] ?? null) === $m['date'] && ($x['court']['name'] ?? null) === $m['court']['name']
                && ! empty($x['time']) && self::min($x['time']) < self::min($m['time'])) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] folded names of everyone in the match */
    private static function people(array $m): array
    {
        return array_values(array_map(fn ($p) => PushSubscription::key(trim(($p['name'] ?? '').' '.($p['surname'] ?? ''))), $m['participants'] ?? []));
    }

    private static function signature(array $m): string
    {
        $p = self::people($m);
        sort($p);

        return implode('|', $p);
    }

    private static function teams(array $m): string
    {
        $side = function (int $s) use ($m) {
            $team = $s === 1 ? ($m['team1'] ?? null) : ($m['team2'] ?? null);
            if (! empty($team['title'])) {
                return $team['title'];
            }
            $names = array_map(fn ($p) => mb_substr(trim($p['name'] ?? ''), 0, 1).'. '.trim($p['surname'] ?? ''),
                array_filter($m['participants'] ?? [], fn ($p) => ($p['side'] ?? null) === $s));

            return $names ? implode(' / ', $names) : '?';
        };

        return $side(1).' – '.$side(2);
    }

    /** "Kortas 4", or "Kortą 4" after "į" (accusative). */
    private static function court(array $m, bool $accusative = false): string
    {
        $name = $m['court']['name'] ?? '';
        $word = $accusative ? 'Kortą' : 'Kortas';

        return preg_match('/\d+/', $name, $n) ? $word.' '.$n[0] : ($name ?: mb_strtolower($word));
    }

    private static function played(array $m): bool
    {
        return ! empty($m['sets']) || ! empty($m['winner_side']) || ! empty($m['is_walkover']);
    }

    private static function min(string $hhmm): int
    {
        [$h, $i] = array_map('intval', explode(':', $hhmm) + [0, 0]);

        return $h * 60 + $i;
    }
}
