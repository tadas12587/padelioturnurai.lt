<?php

namespace Tests\Feature;

use App\Http\Controllers\ScheduleController;
use App\Models\ScheduleSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubLeagueStandingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_league_points_come_from_the_ranking_standing(): void
    {
        $payload = ['standings' => [
            ['source' => 'other', 'entries' => [['position' => 1, 'points' => 99, 'team_name' => 'X']]],
            ['source' => 'ranking', 'entries' => [
                ['position' => 2, 'points' => 9, 'team_name' => '4Padel'],
                ['position' => 1, 'points' => 12, 'team_name' => 'Slėnio Padelio Klubas'],
            ]],
        ]];

        $this->assertSame([
            ['position' => 1, 'club' => 'Slėnio Padelio Klubas', 'points' => 12],
            ['position' => 2, 'club' => '4Padel', 'points' => 9],
        ], ScheduleController::publicLeaguePoints($payload));
    }

    public function test_no_league_points_before_the_stage_is_ranked(): void
    {
        $payload = ['standings' => [['source' => 'ranking', 'entries' => [
            ['position' => 1, 'points' => 0, 'team_name' => '4Padel'],
        ]]]];

        $this->assertSame([], ScheduleController::publicLeaguePoints($payload));
        $this->assertSame([], ScheduleController::publicLeaguePoints([]));
    }

    public function test_data_endpoint_exposes_league_points_and_meeting_scores(): void
    {
        ScheduleSnapshot::create(['tournament_external_id' => '777', 'payload' => [
            'tournament' => ['name' => 'Lyga'],
            'matches' => [],
            'ties' => [[
                'match_id' => 1, 'team1' => ['title' => 'A'], 'team2' => ['title' => 'B'],
                'winner_side' => null, 'score' => [6, 6], 'players' => [],
            ]],
            'standings' => [['source' => 'ranking', 'entries' => [['position' => 1, 'points' => 12, 'team_name' => 'A']]]],
        ]]);

        $this->getJson('/grafikas/777/data')
            ->assertOk()
            ->assertJsonPath('ties.0.score', [6, 6])
            ->assertJsonPath('league_points.0.points', 12);
    }
}
