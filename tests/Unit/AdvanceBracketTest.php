<?php

namespace Tests\Unit;

use App\Models\Overlay;
use PHPUnit\Framework\TestCase;

class AdvanceBracketTest extends TestCase
{
    public function test_advance_fills_later_rounds_and_third_place(): void
    {
        $bracketData = ['size' => 8, 'matches' => [
            ['round' => 'Pusfinaliai', 'team1' => 'A', 'team2' => 'B', 'sets1' => '6 6', 'sets2' => '2 3', 'winner' => 1],
            ['round' => 'Pusfinaliai', 'team1' => 'C', 'team2' => 'D', 'sets1' => '', 'sets2' => '', 'winner' => 2],
            ['round' => 'Finalas', 'team1' => '', 'team2' => '', 'sets1' => '', 'sets2' => '', 'winner' => null],
            ['round' => 'Dėl 3 vietos', 'team1' => '', 'team2' => '', 'sets1' => '', 'sets2' => '', 'winner' => null],
        ]];

        $out = Overlay::advanceBracket($bracketData)['matches'];

        // Final (index 2) filled with the semifinal winners
        $this->assertSame('A', $out[2]['team1']);
        $this->assertSame('D', $out[2]['team2']);
        // 3rd place (index 3) filled with the semifinal losers
        $this->assertSame('B', $out[3]['team1']);
        $this->assertSame('C', $out[3]['team2']);
        // First round untouched
        $this->assertSame('A', $out[0]['team1']);
    }

    public function test_advance_preserves_manual_names(): void
    {
        $bracketData = ['size' => 8, 'matches' => [
            ['round' => 'Pusfinaliai', 'team1' => 'A', 'team2' => 'B', 'sets1' => '', 'sets2' => '', 'winner' => 1],
            ['round' => 'Pusfinaliai', 'team1' => 'C', 'team2' => 'D', 'sets1' => '', 'sets2' => '', 'winner' => 2],
            ['round' => 'Finalas', 'team1' => 'Rankinis', 'team2' => '', 'sets1' => '', 'sets2' => '', 'winner' => null],
        ]];

        $out = Overlay::advanceBracket($bracketData)['matches'];

        $this->assertSame('Rankinis', $out[2]['team1']); // manual override kept
        $this->assertSame('D', $out[2]['team2']);         // empty slot derived
    }
}
