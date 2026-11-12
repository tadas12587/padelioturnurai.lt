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

    public function test_next_match_starts_five_minutes_after_result_and_chain_keeps_planned_gaps(): void
    {
        // slot 50 min; result of the 10:00 match entered 11:02
        $out = CourtDelays::apply([
            $this->match(1, '10:00', seenLocal: '11:02'),
            $this->match(2, '10:50'),
            $this->match(3, '11:40'),
            $this->match(4, '12:30'),
        ], $this->at('11:03'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('11:07', $m[2]['eta']);
        $this->assertSame(17, $m[2]['eta_delay']);
        $this->assertSame('11:57', $m[3]['eta']);
        $this->assertSame('12:47', $m[4]['eta']);
        $this->assertSame(17, $out['courts']['Court 1']);
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
        $this->assertSame('11:50', $m[3]['eta']);
        $this->assertSame(10, $m[3]['eta_delay']);
        $this->assertSame('12:40', $m[4]['eta']);
    }

    public function test_never_shows_more_than_fifteen_minutes_early(): void
    {
        $out = CourtDelays::apply([
            $this->match(1, '10:00', seenLocal: '10:20'), // very quick match
            $this->match(2, '10:50'),
        ], $this->at('10:21'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('10:35', $m[2]['eta']); // 10:25 would be 25 min early → capped at −15
        $this->assertSame(-15, $m[2]['eta_delay']);
    }

    public function test_a_running_match_past_its_slot_pushes_the_next_one_live(): void
    {
        // match 2 should start 11:07 and end ~11:52; at 12:10 it is still running
        $out = CourtDelays::apply([
            $this->match(1, '10:00', seenLocal: '11:02'),
            $this->match(2, '10:50'),
            $this->match(3, '11:40'),
            $this->match(4, '12:30'),
        ], $this->at('12:10'), self::TZ);

        $m = $this->byId($out);
        $this->assertSame('12:15', $m[3]['eta']); // now + 5
        $this->assertSame(35, $m[3]['eta_delay']);
        $this->assertSame('13:05', $m[4]['eta']);
        $this->assertSame(35, $out['courts']['Court 1']);
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

        $this->assertSame(17, $out['courts']['Court 1']);
        $this->assertSame(7, $out['courts']['Court 2']);
        $this->assertArrayNotHasKey('eta', $this->byId($out)[9]);
    }

    public function test_repeated_surname_in_tournated_name_is_removed(): void
    {
        $this->assertSame('Simona', MatchShape::person(1, 'Simona Diržinskaitė', 'Diržinskaitė')['name']);
        $this->assertSame('Robertas', MatchShape::person(1, 'Robertas ', 'Šimkus')['name']);
        $this->assertSame('Jonas', MatchShape::person(2, 'Jonas', 'Jonas')['name']); // single-word profile untouched
    }
}
