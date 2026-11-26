<?php

namespace Tests\Unit;

use App\Services\Tournated\CourtDelays;
use App\Services\Tournated\MatchShape;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CourtDelaysTest extends TestCase
{
    private const TZ = 'Europe/Vilnius';

    private function match(int $id, string $time, string $court = 'Court 1', ?string $seenLocal = null, bool $batch = false): array
    {
        $m = ['match_id' => $id, 'date' => '2026-11-14', 'time' => $time, 'court' => ['name' => $court], 'sets' => [], 'winner_side' => null];
        if ($seenLocal) {
            $m['sets'] = [['side1' => 6, 'side2' => 4]];
            $m['winner_side'] = 1;
            $m['result_seen_at'] = CarbonImmutable::parse("2026-11-14 {$seenLocal}", self::TZ)->toIso8601String();
            $m['result_batch'] = $batch;
        }

        return $m;
    }

    private function at(string $localTime): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-11-14 {$localTime}", self::TZ);
    }

    private function byId(array $out): array
    {
        return array_column($out['matches'], null, 'match_id');
    }

    public function test_next_match_starts_when_result_is_entered_and_chain_keeps_planned_gaps(): void
    {
        // slot 50 min; result of the 10:00 match entered 11:02
        $out = CourtDelays::apply([
            $this->match(1, '10:00', seenLocal: '11:02'),
            $this->match(2, '10:50'),
            $this->match(3, '11:40'),
            $this->match(4, '12:30'),
        ], $this->at('11:03'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('11:02', $m[2]['eta']);
        $this->assertSame(12, $m[2]['eta_delay']);
        $this->assertSame('11:52', $m[3]['eta']);
        $this->assertSame('12:42', $m[4]['eta']);
        $this->assertSame(12, $out['courts']['Court 1']);
        $this->assertArrayNotHasKey('eta', $m[1]); // played match keeps its own time
    }

    public function test_a_fast_match_pulls_the_schedule_back(): void
    {
        $out = CourtDelays::apply([
            $this->match(1, '10:00', seenLocal: '11:02'),
            $this->match(2, '10:50', seenLocal: '11:45'),
            $this->match(3, '11:40'),
            $this->match(4, '12:30'),
        ], $this->at('11:46'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('11:45', $m[3]['eta']);
        $this->assertSame(5, $m[3]['eta_delay']);
        $this->assertSame('12:35', $m[4]['eta']);
    }

    public function test_next_starts_at_once_but_later_ones_are_never_more_than_fifteen_minutes_early(): void
    {
        $out = CourtDelays::apply([
            $this->match(1, '10:00', seenLocal: '10:20'), // very quick match
            $this->match(2, '10:50'),
            $this->match(3, '11:40'),
        ], $this->at('10:21'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('10:20', $m[2]['eta']); // already on court
        $this->assertSame(-30, $m[2]['eta_delay']);
        $this->assertSame('11:25', $m[3]['eta']); // 11:10 would be 30 min early → capped at −15
    }

    public function test_a_running_match_past_its_slot_pushes_the_next_one_live(): void
    {
        // match 2 started 11:02 and should end ~11:52; at 12:10 it is still running
        $out = CourtDelays::apply([
            $this->match(1, '10:00', seenLocal: '11:02'),
            $this->match(2, '10:50'),
            $this->match(3, '11:40'),
            $this->match(4, '12:30'),
        ], $this->at('12:10'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('12:10', $m[3]['eta']); // now
        $this->assertSame(30, $m[3]['eta_delay']);
        $this->assertSame('13:00', $m[4]['eta']);
        $this->assertSame(30, $out['courts']['Court 1']);
    }

    public function test_results_entered_in_a_batch_or_seen_before_tracking_are_not_trusted(): void
    {
        $batch = CourtDelays::apply([
            $this->match(1, '10:00', seenLocal: '23:40', batch: true),
            $this->match(2, '10:50'),
        ], $this->at('23:41'), self::TZ);
        $this->assertArrayNotHasKey('eta', $this->byId($batch)[2]);
        $this->assertArrayNotHasKey('Court 1', $batch['courts']);

        $unknown = $this->match(1, '10:00', seenLocal: '11:02');
        $unknown['result_seen_at'] = null;
        $out = CourtDelays::apply([$unknown, $this->match(2, '10:50')], $this->at('11:03'), self::TZ);
        $this->assertArrayNotHasKey('eta', $this->byId($out)[2]);
    }

    public function test_courts_are_independent_and_other_days_are_ignored(): void
    {
        $tomorrow = $this->match(9, '10:00', 'Court 2');
        $tomorrow['date'] = '2026-11-15';

        $out = CourtDelays::apply([
            $this->match(1, '10:00', 'Court 1', '11:02'),
            $this->match(2, '10:50', 'Court 1'),
            $this->match(3, '10:00', 'Court 2', '10:52'),
            $this->match(4, '10:50', 'Court 2'),
            $tomorrow,
        ], $this->at('11:03'), self::TZ);

        $this->assertSame(12, $out['courts']['Court 1']);
        $this->assertSame(2, $out['courts']['Court 2']);
        $this->assertArrayNotHasKey('eta', $this->byId($out)[9]);
    }

    public function test_repeated_surname_in_tournated_name_is_removed(): void
    {
        $this->assertSame('Simona', MatchShape::person(1, 'Simona Diržinskaitė', 'Diržinskaitė')['name']);
        $this->assertSame('Robertas', MatchShape::person(1, 'Robertas ', 'Šimkus')['name']);
        $this->assertSame('Jonas', MatchShape::person(2, 'Jonas', 'Jonas')['name']); // single-word profile untouched
    }

    public function test_a_reserve_hour_absorbs_the_delay_for_the_next_session(): void
    {
        // 50-min slots, 14:10 is the last before the reserve hour, then 16:00
        $out = CourtDelays::apply([
            $this->match(1, '13:20', seenLocal: '14:35'), // 25 min late
            $this->match(2, '14:10'),
            $this->match(3, '16:00'),
            $this->match(4, '16:50'),
        ], $this->at('14:36'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('14:35', $m[2]['eta']);
        $this->assertArrayNotHasKey('eta', $m[3]); // 14:35 + 50 = 15:25 < 16:00 → on time
        $this->assertArrayNotHasKey('eta', $m[4]);
    }

    public function test_an_overrun_longer_than_the_break_still_pushes_the_next_session(): void
    {
        $out = CourtDelays::apply([
            $this->match(1, '13:20', seenLocal: '15:30'), // 85 min late
            $this->match(2, '14:10'),
            $this->match(3, '16:00'),
        ], $this->at('15:31'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('15:30', $m[2]['eta']);
        $this->assertSame('16:20', $m[3]['eta']); // 15:30 + 50
        $this->assertSame(20, $m[3]['eta_delay']);
    }

    public function test_a_late_result_before_the_break_does_not_pull_the_next_session_early_or_late(): void
    {
        $out = CourtDelays::apply([
            $this->match(0, '13:20', seenLocal: '14:15'),
            $this->match(1, '14:10', seenLocal: '15:20'),
            $this->match(2, '16:00'),
        ], $this->at('15:21'), self::TZ);

        $this->assertArrayNotHasKey('eta', $this->byId($out)[2]);
    }

    public function test_a_match_running_long_before_the_break_does_not_push_past_the_break(): void
    {
        $out = CourtDelays::apply([
            $this->match(1, '13:20', seenLocal: '14:12'),
            $this->match(2, '14:10'),
            $this->match(3, '16:00'),
        ], $this->at('15:30'), self::TZ); // 14:15 match still going at 15:30

        $this->assertArrayNotHasKey('eta', $this->byId($out)[3]);
    }
}
