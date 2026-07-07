<?php

namespace App\Services;

/**
 * Pure padel scoring: points → games → sets → match, with advantage/golden/STAR
 * deuce, tiebreaks, super-tiebreak, serve rotation and undo. No DB/HTTP.
 * Operates as (config, state) → new state. The live state lives in
 * overlay.state['score'].
 */
class ScoreEngine
{
    /** Normalise window keys → engine config. */
    public function config(array $w): array
    {
        $gps = (int) ($w['score_games_per_set'] ?? 6);

        return [
            'games_per_set' => $gps,
            'tiebreak_at'   => (int) ($w['score_tiebreak_at'] ?? $gps),
            'sets_to_win'   => (int) ($w['score_sets_to_win'] ?? 2),
            'tiebreak'      => (bool) ($w['score_tiebreak'] ?? true),
            'tiebreak_to'   => (int) ($w['score_tiebreak_to'] ?? 7),
            'super_tb'      => (bool) ($w['score_super_tb'] ?? true),
            'super_tb_to'   => (int) ($w['score_super_tb_to'] ?? 10),
            'deuce_mode'    => $w['score_deuce_mode'] ?? 'star',
        ];
    }

    /** @param  array<int,mixed>  $teams  [[p1,p2],[p3,p4]] */
    public function init(array $config, array $teams): array
    {
        return [
            'teams' => array_values($teams),
            'sets' => [], 'sets_won' => [0, 0], 'games' => [0, 0], 'points' => [0, 0],
            'adv' => null, 'star_stage' => 0,
            'tiebreak' => false, 'super_tiebreak' => false, 'tb' => [0, 0], 'tb_start_server' => 0,
            'server_team' => 0, 'server_player' => 0, 'next_player' => [1, 0],
            'status' => 'playing', 'winner' => null, 'history' => [],
        ];
    }

    public function point(array $config, array $state, int $team): array
    {
        if ($state['status'] === 'finished') {
            return $state;
        }
        $state = $this->pushHistory($state);

        return $state['tiebreak']
            ? $this->tbPoint($config, $state, $team)
            : $this->gamePoint($config, $state, $team);
    }

    public function undo(array $config, array $state): array
    {
        if (empty($state['history'])) {
            return $state;
        }
        $history = $state['history'];
        $prev = array_pop($history);
        $prev['history'] = $history;

        return $prev;
    }

    public function reset(array $config, array $state): array
    {
        return $this->init($config, $state['teams']);
    }

    public function setServer(array $state, int $team, int $player = 0): array
    {
        $state['server_team'] = $team;
        $state['server_player'] = $player;
        $state['next_player'] = [$team === 0 ? 1 - $player : 0, $team === 1 ? 1 - $player : 0];

        return $state;
    }

    private function pushHistory(array $state): array
    {
        $snap = $state;
        unset($snap['history']);
        $state['history'][] = $snap;
        if (count($state['history']) > 400) {
            array_shift($state['history']);
        }

        return $state;
    }

    private function gamePoint(array $config, array $state, int $team): array
    {
        $p = $state['points'];
        $bothAt40 = $p[0] >= 3 && $p[1] >= 3;

        if (! $bothAt40) {
            if ($p[$team] < 3) {
                $state['points'][$team]++;

                return $state;
            }

            return $this->awardGame($config, $state, $team);
        }

        // both at 40 — deuce logic per mode
        $mode = $config['deuce_mode'];

        if ($mode === 'golden') {
            return $this->awardGame($config, $state, $team);
        }

        if ($mode === 'advantage') {
            if ($state['adv'] === null) {
                $state['adv'] = $team;

                return $state;
            }
            if ($state['adv'] === $team) {
                return $this->awardGame($config, $state, $team);
            }
            $state['adv'] = null; // lost advantage → back to deuce

            return $state;
        }

        // star: first advantage → deuce → second advantage → star point
        $stage = $state['star_stage'];
        if ($stage === 0) {
            $state['adv'] = $team;
            $state['star_stage'] = 'adv1';

            return $state;
        }
        if ($stage === 'adv1') {
            if ($state['adv'] === $team) {
                return $this->awardGame($config, $state, $team);
            }
            $state['adv'] = null;
            $state['star_stage'] = 1;

            return $state;
        }
        if ($stage === 1) {
            $state['adv'] = $team;
            $state['star_stage'] = 'adv2';

            return $state;
        }
        if ($stage === 'adv2') {
            if ($state['adv'] === $team) {
                return $this->awardGame($config, $state, $team);
            }
            $state['adv'] = null;
            $state['star_stage'] = 'star';

            return $state;
        }

        return $this->awardGame($config, $state, $team); // star point
    }

    private function awardGame(array $config, array $state, int $team): array
    {
        $state['games'][$team]++;
        $state['points'] = [0, 0];
        $state['adv'] = null;
        $state['star_stage'] = 0;
        $state = $this->rotateServer($state);

        return $this->checkSet($config, $state);
    }

    private function checkSet(array $config, array $state): array
    {
        $g = $state['games'];
        $gps = $config['games_per_set'];

        if ($g[0] >= $gps && $g[0] - $g[1] >= 2) {
            return $this->awardSet($config, $state, 0, [$g[0], $g[1]]);
        }
        if ($g[1] >= $gps && $g[1] - $g[0] >= 2) {
            return $this->awardSet($config, $state, 1, [$g[0], $g[1]]);
        }

        if ($config['tiebreak'] && $g[0] >= $config['tiebreak_at'] && $g[1] >= $config['tiebreak_at']) {
            $state['tiebreak'] = true;
            $state['tb'] = [0, 0];
            $state['tb_start_server'] = $state['server_team'];
        }

        return $state;
    }

    private function awardSet(array $config, array $state, int $team, array $score): array
    {
        $state['sets'][] = $score;
        $state['sets_won'][$team]++;
        $state['games'] = [0, 0];
        $state['points'] = [0, 0];
        $state['adv'] = null;
        $state['star_stage'] = 0;
        $state['tiebreak'] = false;
        $state['tb'] = [0, 0];

        if ($state['sets_won'][$team] >= $config['sets_to_win']) {
            $state['status'] = 'finished';
            $state['winner'] = $team;

            return $state;
        }

        return $this->maybeStartSuperTb($config, $state);
    }

    private function maybeStartSuperTb(array $config, array $state): array
    {
        $need = $config['sets_to_win'] - 1;
        if ($config['super_tb'] && $state['sets_won'][0] === $need && $state['sets_won'][1] === $need) {
            $state['super_tiebreak'] = true;
            $state['tiebreak'] = true;
            $state['tb'] = [0, 0];
            $state['tb_start_server'] = $state['server_team'];
        }

        return $state;
    }

    private function rotateServer(array $state): array
    {
        $newTeam = 1 - $state['server_team'];
        $player = $state['next_player'][$newTeam];
        $state['server_team'] = $newTeam;
        $state['server_player'] = $player;
        $state['next_player'][$newTeam] = 1 - $player;

        return $state;
    }

    private function tbPoint(array $config, array $state, int $team): array
    {
        $state['tb'][$team]++;
        $target = $state['super_tiebreak'] ? $config['super_tb_to'] : $config['tiebreak_to'];

        // Serve indicator: A serves point 1, then serve switches every 2 points.
        $k = $state['tb'][0] + $state['tb'][1] + 1; // next point number
        $state['server_team'] = ($state['tb_start_server'] + intdiv($k, 2)) % 2;

        $t = $state['tb'];
        if ($t[$team] >= $target && $t[$team] - $t[1 - $team] >= 2) {
            return $this->awardTiebreak($config, $state, $team);
        }

        return $state;
    }

    private function awardTiebreak(array $config, array $state, int $team): array
    {
        if ($state['super_tiebreak']) {
            $state['sets'][] = [$state['tb'][0], $state['tb'][1]];
            $state['sets_won'][$team]++;
            $state['status'] = 'finished';
            $state['winner'] = $team;

            return $state;
        }

        $wa = $config['tiebreak_at'];
        $score = $team === 0 ? [$wa + 1, $wa] : [$wa, $wa + 1];

        return $this->awardSet($config, $state, $team, $score);
    }
}
