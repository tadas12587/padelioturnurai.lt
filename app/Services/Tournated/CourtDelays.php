<?php

namespace App\Services\Tournated;

use Carbon\CarbonImmutable;

/**
 * Automatic per-court schedule estimate for today's matches.
 *
 * When the referee enters a result, the next match on that court is
 * expected to start at (moment the result appeared) + changeover, and every
 * later match keeps its planned gap from the previous one. A match still
 * running past its planned slot pushes the next one live (now + changeover).
 *
 * Breaks: a planned gap noticeably longer than the court's normal slot (e.g. a
 * reserve hour between sessions) absorbs the delay — the match after the
 * break is expected at max(planned time, previous expected start + slot), so
 * players of a later session keep their planned time unless the earlier
 * session overruns by more than the break.
 *
 * Safeguards: results we did not see arrive live (first sync, or several on
 * one court in the same minute = entered in a batch) are not trusted, so that
 * court gets no estimate; an estimate is never more than EARLY_CAP minutes
 * before the planned time, nor more than LATE_CAP after.
 */
class CourtDelays
{
    public const CHANGEOVER = 5;

    public const EARLY_CAP = 15;

    public const LATE_CAP = 180;

    /**
     * @return array{matches: array, courts: array<string,int>}
     *   matches: input with 'eta' (HH:MM) and 'eta_delay' (minutes) on estimated ones
     *   courts:  court name => current delay in minutes (negative = ahead)
     */
    public static function apply(array $matches, CarbonImmutable $now, string $tz = 'Europe/Vilnius'): array
    {
        $local = $now->setTimezone($tz);
        $today = $local->toDateString();
        $nowMin = $local->hour * 60 + $local->minute;

        $byCourt = [];
        foreach ($matches as $i => $m) {
            if (($m['date'] ?? null) === $today && ! empty($m['time']) && ! empty($m['court']['name'])) {
                $byCourt[$m['court']['name']][] = $i;
            }
        }

        $courts = [];
        foreach ($byCourt as $court => $idx) {
            usort($idx, fn ($a, $b) => strcmp($matches[$a]['time'], $matches[$b]['time']));
            $slot = self::slot(array_map(fn ($i) => $matches[$i]['time'], $idx));

            $anchor = null;
            foreach ($idx as $k => $i) {
                if (self::played($matches[$i])) {
                    $anchor = $k;
                }
            }
            $pending = array_values(array_filter(
                array_slice($idx, $anchor === null ? 0 : $anchor + 1),
                fn ($i) => ! self::played($matches[$i]),
            ));
            if (! $pending) {
                continue;
            }

            $eta = null;
            if ($anchor !== null) {
                $a = $matches[$idx[$anchor]];
                if (empty($a['result_seen_at']) || ! empty($a['result_batch'])) {
                    continue; // result not seen arriving live — cannot trust its time
                }
                $seen = CarbonImmutable::parse($a['result_seen_at'])->setTimezone($tz);
                if ($seen->toDateString() !== $today) {
                    continue;
                }
                $eta = $seen->hour * 60 + $seen->minute + self::CHANGEOVER;
            }

            $prevPlanned = null;
            $prevEta = null;
            if ($anchor !== null) {
                $anchorPlanned = self::minutes($matches[$idx[$anchor]]['time']);
                if (self::isBreak(self::minutes($matches[$pending[0]]['time']) - $anchorPlanned, $slot)) {
                    // the result came in before a break: the next session keeps its planned start
                    $eta = max(self::minutes($matches[$pending[0]]['time']), $eta);
                }
            }
            $courtDelay = null;
            foreach ($pending as $n => $i) {
                $planned = self::minutes($matches[$i]['time']);
                if ($n === 0) {
                    $e = $eta ?? $planned;
                } else {
                    $gap = $planned - $prevPlanned;
                    $e = self::isBreak($gap, $slot)
                        ? max($planned, $prevEta + $slot)
                        : $prevEta + $gap;
                    // previous match already started and is running past its slot
                    $prevExpectedEnd = $prevEta + min($gap, $slot) - self::CHANGEOVER;
                    if ($nowMin >= $prevEta && $nowMin > $prevExpectedEnd) {
                        $e = max($e, $nowMin + self::CHANGEOVER);
                    }
                }
                $e = max($e, $planned - self::EARLY_CAP);
                $e = min($e, $planned + self::LATE_CAP);

                $delay = $e - $planned;
                if ($delay !== 0) {
                    $matches[$i]['eta'] = self::hhmm($e);
                    $matches[$i]['eta_delay'] = $delay;
                }
                if ($courtDelay === null && $e > $nowMin) {
                    $courtDelay = $delay;
                }
                $prevPlanned = $planned;
                $prevEta = $e;
            }
            $courts[$court] = $courtDelay ?? ($prevEta - $prevPlanned);
        }

        return ['matches' => $matches, 'courts' => $courts];
    }

    /**
     * The court's normal slot: the smallest gap between consecutive planned
     * times (50 min when it can't be told).
     *
     * @param  list<string>  $times  sorted HH:MM
     */
    public static function slot(array $times): int
    {
        $gaps = [];
        for ($k = 1; $k < count($times); $k++) {
            $g = self::minutes($times[$k]) - self::minutes($times[$k - 1]);
            if ($g > 0) {
                $gaps[] = $g;
            }
        }

        return $gaps ? min($gaps) : 50;
    }

    /** A planned gap of more than 1.5 slots is a break that absorbs delay. */
    public static function isBreak(int $gap, int $slot): bool
    {
        return $gap > $slot * 1.5;
    }

    private static function played(array $m): bool
    {
        return ! empty($m['sets']) || ! empty($m['winner_side']) || ! empty($m['is_walkover']);
    }

    private static function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm) + [0, 0]);

        return $h * 60 + $m;
    }

    private static function hhmm(int $minutes): string
    {
        $minutes = max(0, min($minutes, 23 * 60 + 59));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
