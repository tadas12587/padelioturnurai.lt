<?php

namespace Tests\Unit;

use App\Services\ScoreEngine;
use Tests\TestCase;

class ScoreEngineTest extends TestCase
{
    private ScoreEngine $e;
    private array $cfg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e = new ScoreEngine();
        $this->cfg = $this->e->config(['score_deuce_mode' => 'golden', 'score_sets_to_win' => 1, 'score_tiebreak' => false]);
    }

    private function play(array $state, array $seq, ?array $cfg = null): array
    {
        $cfg ??= $this->cfg;
        foreach ($seq as $team) {
            $state = $this->e->point($cfg, $state, $team);
        }

        return $state;
    }

    public function test_points_progress_and_win_a_game(): void
    {
        $s = $this->e->init($this->cfg, [['A B'], ['C D']]);
        $s = $this->play($s, [0, 0, 0]);
        $this->assertSame([3, 0], $s['points']);
        $s = $this->play($s, [0]);
        $this->assertSame([1, 0], $s['games']);
        $this->assertSame([0, 0], $s['points']);
    }

    public function test_win_a_set_by_two_and_finish_single_set_match(): void
    {
        $s = $this->e->init($this->cfg, [['A B'], ['C D']]);
        for ($g = 0; $g < 6; $g++) {
            $s = $this->play($s, [0, 0, 0, 0]);
        }
        $this->assertSame('finished', $s['status']);
        $this->assertSame(0, $s['winner']);
        $this->assertSame([[6, 0]], $s['sets']);
    }

    public function test_serve_alternates_each_game(): void
    {
        $s = $this->e->init($this->cfg, [['A B'], ['C D']]);
        $this->assertSame(0, $s['server_team']);
        $s = $this->play($s, [0, 0, 0, 0]);
        $this->assertSame(1, $s['server_team']);
    }

    public function test_undo_reverts_last_point(): void
    {
        $s = $this->e->init($this->cfg, [['A B'], ['C D']]);
        $s = $this->play($s, [0, 0]);
        $this->assertSame([2, 0], $s['points']);
        $s = $this->e->undo($this->cfg, $s);
        $this->assertSame([1, 0], $s['points']);
    }

    public function test_advantage_needs_two_points(): void
    {
        $cfg = $this->e->config(['score_deuce_mode' => 'advantage', 'score_sets_to_win' => 1, 'score_tiebreak' => false]);
        $s = $this->e->init($cfg, [['A'], ['B']]);
        $s = $this->play($s, [0, 0, 0, 1, 1, 1], $cfg);
        $s = $this->e->point($cfg, $s, 0);
        $this->assertSame(0, $s['adv']);
        $this->assertSame([0, 0], $s['games']);
        $s = $this->e->point($cfg, $s, 1);
        $this->assertNull($s['adv']);
        $s = $this->e->point($cfg, $s, 0);
        $s = $this->e->point($cfg, $s, 0);
        $this->assertSame([1, 0], $s['games']);
    }

    public function test_golden_point_decides_at_deuce(): void
    {
        $cfg = $this->e->config(['score_deuce_mode' => 'golden', 'score_sets_to_win' => 1, 'score_tiebreak' => false]);
        $s = $this->e->init($cfg, [['A'], ['B']]);
        $s = $this->play($s, [0, 0, 0, 1, 1, 1], $cfg);
        $s = $this->e->point($cfg, $s, 1);
        $this->assertSame([0, 1], $s['games']);
    }

    public function test_star_point_sequence(): void
    {
        $cfg = $this->e->config(['score_deuce_mode' => 'star', 'score_sets_to_win' => 1, 'score_tiebreak' => false]);
        $s = $this->e->init($cfg, [['A'], ['B']]);
        $s = $this->play($s, [0, 0, 0, 1, 1, 1], $cfg);
        $s = $this->e->point($cfg, $s, 0);
        $this->assertSame('adv1', $s['star_stage']);
        $s = $this->e->point($cfg, $s, 1);
        $this->assertSame(1, $s['star_stage']);
        $s = $this->e->point($cfg, $s, 1);
        $this->assertSame('adv2', $s['star_stage']);
        $s = $this->e->point($cfg, $s, 0);
        $this->assertSame('star', $s['star_stage']);
        $s = $this->e->point($cfg, $s, 0);
        $this->assertSame([1, 0], $s['games']);
    }

    public function test_tiebreak_triggers_and_is_won_by_two(): void
    {
        $cfg = $this->e->config(['score_deuce_mode' => 'golden', 'score_sets_to_win' => 1, 'score_tiebreak' => true, 'score_tiebreak_to' => 7]);
        $s = $this->e->init($cfg, [['A'], ['B']]);
        $winGame = function ($s, $t) use ($cfg) {
            foreach ([$t, $t, $t, $t] as $x) {
                $s = $this->e->point($cfg, $s, $x);
            }

            return $s;
        };
        for ($i = 0; $i < 6; $i++) {
            $s = $winGame($s, 0);
            $s = $winGame($s, 1);
        }
        $this->assertTrue($s['tiebreak']);
        for ($i = 0; $i < 7; $i++) {
            $s = $this->e->point($cfg, $s, 0);
        }
        $this->assertSame('finished', $s['status']);
        $this->assertSame(0, $s['winner']);
        $this->assertSame([[7, 6]], $s['sets']);
    }

    public function test_tiebreak_at_eight_for_to_nine_set(): void
    {
        $cfg = $this->e->config(['score_games_per_set' => 9, 'score_tiebreak_at' => 8, 'score_sets_to_win' => 1, 'score_deuce_mode' => 'golden']);
        $s = $this->e->init($cfg, [['A'], ['B']]);
        $winGame = function ($s, $t) use ($cfg) {
            foreach ([$t, $t, $t, $t] as $x) {
                $s = $this->e->point($cfg, $s, $x);
            }

            return $s;
        };
        for ($i = 0; $i < 8; $i++) {
            $s = $winGame($s, 0);
            $s = $winGame($s, 1);
        }
        $this->assertTrue($s['tiebreak']);
    }

    public function test_super_tiebreak_decides_best_of_three(): void
    {
        $cfg = $this->e->config(['score_deuce_mode' => 'golden', 'score_sets_to_win' => 2, 'score_super_tb' => true, 'score_super_tb_to' => 10]);
        $s = $this->e->init($cfg, [['A'], ['B']]);
        $winSet = function ($s, $t) use ($cfg) {
            for ($g = 0; $g < 6; $g++) {
                foreach ([$t, $t, $t, $t] as $x) {
                    $s = $this->e->point($cfg, $s, $x);
                }
            }

            return $s;
        };
        $s = $winSet($s, 0);
        $s = $winSet($s, 1);
        $this->assertTrue($s['tiebreak']);
        $this->assertTrue($s['super_tiebreak']);
        for ($i = 0; $i < 10; $i++) {
            $s = $this->e->point($cfg, $s, 0);
        }
        $this->assertSame('finished', $s['status']);
        $this->assertSame(0, $s['winner']);
    }
}
