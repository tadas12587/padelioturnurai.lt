<?php

namespace App\Console\Commands;

use App\Models\ScheduleSnapshot;
use App\Models\TournatedSync;
use App\Services\Tournated\TournamentSync;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Run every minute by the scheduler (host CRON). Each enabled tournament is
 * synced every minute around its dates, every 30 min before, daily after.
 */
class TournatedSyncCommand extends Command
{
    protected $signature = 'tournated:sync
        {tournament?* : Tournated turnyro ID (jei nenurodyta – visi įjungti)}
        {--add : Pridėti nurodytus turnyrus į automatinį sinchronizavimą}
        {--force : Sinchronizuoti dabar, nepaisant intervalo}';

    protected $description = 'Parsiunčia turnyrų duomenis iš Tournated į svetainę';

    public function handle(): int
    {
        $ids = $this->argument('tournament');

        if ($this->option('add')) {
            foreach ($ids as $id) {
                TournatedSync::updateOrCreate(['tournament_external_id' => (string) $id], ['enabled' => true]);
                $this->info("Įjungtas automatinis sinchronizavimas: {$id}");
            }
        }

        $query = TournatedSync::query();
        $ids ? $query->whereIn('tournament_external_id', $ids) : $query->where('enabled', true);
        $syncs = $query->get();

        if ($ids && $syncs->isEmpty()) {
            // One-off run for a tournament that is not (yet) tracked.
            $syncs = collect($ids)->map(fn ($id) => new TournatedSync(['tournament_external_id' => (string) $id]));
        }

        $service = null;
        $failed = 0;
        foreach ($syncs as $sync) {
            if (! $this->option('force') && ! $ids && ! $this->isDue($sync)) {
                continue;
            }

            $id = $sync->tournament_external_id;
            $sync->last_attempt_at = now();
            try {
                $service ??= TournamentSync::make();
                $result = $service->sync($id);
                $sync->fill([
                    'last_success_at' => now(),
                    'last_source' => $result['source'],
                    'match_count' => $result['matches'],
                    'last_error' => $result['warnings'] ? implode("\n", $result['warnings']) : null,
                ]);
                $this->info("{$id}: {$result['matches']} mačų per {$result['source']}".($result['warnings'] ? ' (įspėjimų: '.count($result['warnings']).')' : ''));
            } catch (Throwable $e) {
                $failed++;
                $sync->last_error = $e->getMessage();
                $this->error("{$id}: {$e->getMessage()}");
            }
            if ($sync->exists) {
                $sync->save();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function isDue(TournatedSync $sync): bool
    {
        if (! $sync->last_attempt_at) {
            return true;
        }

        $t = ScheduleSnapshot::where('tournament_external_id', $sync->tournament_external_id)->value('payload')['tournament'] ?? [];
        $start = isset($t['start']) ? CarbonImmutable::parse($t['start']) : null;
        $end = isset($t['end']) ? CarbonImmutable::parse($t['end']) : null;
        $now = CarbonImmutable::now();

        $minutes = match (true) {
            ! $start || ! $end => 5,
            $now->between($start->subHours(12), $end->addHours(12)) => 1,
            $now->lt($start) => 30,
            $now->lt($end->addDays(3)) => 60,
            default => 24 * 60,
        };

        return $sync->last_attempt_at->lte($now->subMinutes($minutes)->addSeconds(10));
    }
}
