<?php

namespace Tests\Unit;

use App\Services\OverlayData;
use PHPUnit\Framework\TestCase;

class OverlayDataTest extends TestCase
{
    public function test_compute_standings_counts_wins_and_pairs(): void
    {
        $group = [
            'id'   => 23719,
            'name' => 'U10 Berniukai',
            'entries' => [
                ['id' => 1, 'place' => 1, 'registrationRequest' => ['users' => [
                    ['user' => ['name' => 'Garetas', 'surname' => 'Paplauskas']],
                    ['user' => ['name' => 'Oskaras', 'surname' => 'Žiūkas']],
                ]]],
                ['id' => 2, 'place' => 2, 'registrationRequest' => ['users' => [
                    ['user' => ['name' => 'Jonas', 'surname' => 'Jonaitis']],
                    ['user' => ['name' => 'Petras', 'surname' => 'Petraitis']],
                ]]],
            ],
            'matches' => [
                ['id' => 10, 'status' => 'completed', 'winner' => ['id' => 1]],
            ],
        ];

        $rows = (new OverlayData)->computeStandings($group);

        $this->assertSame(1, $rows[0]['place']);
        $this->assertSame('Garetas Paplauskas / Oskaras Žiūkas', $rows[0]['name']);
        $this->assertSame(1, $rows[0]['wins']);
        $this->assertSame(0, $rows[0]['losses']);
        $this->assertSame(0, $rows[1]['wins']);
        $this->assertSame(1, $rows[1]['losses']);
    }
}
