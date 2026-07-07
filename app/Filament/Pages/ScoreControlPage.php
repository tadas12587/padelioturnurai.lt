<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use App\Services\OverlayData;
use App\Services\ScoreEngine;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ScoreControlPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Rezultatas';
    protected static ?string $title = 'Rezultatas (gyvas)';
    protected static string $view = 'filament.pages.score-control';

    public ?int $overlayId = null;
    public ?string $windowId = null;
    public string $search = '';

    public function selectedOverlay(): ?Overlay
    {
        return $this->overlayId ? Overlay::find($this->overlayId) : null;
    }

    /** @return array<int,string> */
    public function overlayOptions(): array
    {
        return Overlay::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string,string> */
    public function windowOptions(): array
    {
        $out = [];
        foreach ($this->selectedOverlay()?->windows ?? [] as $w) {
            if (($w['type'] ?? null) === 'score') {
                $out[$w['id']] = $w['name'] ?? $w['id'];
            }
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function currentWindow(): ?array
    {
        foreach ($this->selectedOverlay()?->windows ?? [] as $w) {
            if (($w['id'] ?? null) === $this->windowId) {
                return $w;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function scoreState(): array
    {
        return $this->selectedOverlay()?->state['score'] ?? [];
    }

    public function activeMatchId()
    {
        return $this->selectedOverlay()?->state['score_match_id'] ?? null;
    }

    /** Fixtures for the overlay's tournament. @return list<array<string,mixed>> */
    public function matches(): array
    {
        $overlay = $this->selectedOverlay();
        if (! $overlay) {
            return [];
        }
        $needle = mb_strtolower(trim($this->search));
        $rows = app(OverlayData::class)->matches((string) $overlay->tournament_external_id);
        $rows = array_filter($rows, function ($m) use ($needle) {
            if ($needle === '') {
                return true;
            }

            return str_contains(mb_strtolower(implode(' ', array_merge($m['team1'] ?? [], $m['team2'] ?? []))), $needle);
        });

        return array_values(array_map(fn ($m) => [
            'id' => $m['id'] ?? null, 'team1' => $m['team1'] ?? [], 'team2' => $m['team2'] ?? [],
            'time' => $m['time'] ?? null, 'court' => $m['court'] ?? null, 'category' => $m['category'] ?? null,
        ], $rows));
    }

    private function saveState(callable $fn): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $overlay->state = $fn(array_merge(Overlay::defaultState(), $overlay->state ?? []));
        $overlay->save();
    }

    private function hasScore(): bool
    {
        return ! empty($this->selectedOverlay()?->state['score'] ?? []);
    }

    private function engine(): ScoreEngine
    {
        return app(ScoreEngine::class);
    }

    private function config(): array
    {
        return $this->engine()->config($this->currentWindow() ?? []);
    }

    public function selectMatch($matchId): void
    {
        if (! $this->windowId) {
            Notification::make()->title('Pirma pasirink Rezultato langą.')->warning()->send();

            return;
        }
        $overlay = $this->selectedOverlay();
        $m = collect(app(OverlayData::class)->matches((string) $overlay->tournament_external_id))
            ->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $matchId);
        if (! $m) {
            return;
        }
        $teams = [$m['team1'] ?? [], $m['team2'] ?? []];
        $this->saveState(function ($state) use ($teams, $matchId) {
            $state['score'] = $this->engine()->init($this->config(), $teams);
            $state['score_match_id'] = $matchId;
            $state['active_window_id'] = $this->windowId;

            return $state;
        });
        Notification::make()->title('▶ Rodoma')->success()->send();
    }

    public function point(int $team): void
    {
        if (! $this->overlayId || ! $this->hasScore()) {
            return;
        }
        $this->saveState(function ($state) use ($team) {
            $state['score'] = $this->engine()->point($this->config(), $state['score'] ?? [], $team);

            return $state;
        });
    }

    public function undo(): void
    {
        $this->saveState(function ($state) {
            $state['score'] = $this->engine()->undo($this->config(), $state['score'] ?? []);

            return $state;
        });
    }

    public function resetScore(): void
    {
        $this->saveState(function ($state) {
            $state['score'] = $this->engine()->reset($this->config(), $state['score'] ?? []);

            return $state;
        });
    }

    public function setServer(int $team): void
    {
        $this->saveState(function ($state) use ($team) {
            $state['score'] = $this->engine()->setServer($state['score'] ?? [], $team);

            return $state;
        });
    }

    public function stop(): void
    {
        $this->saveState(function ($state) {
            $state['active_window_id'] = null;

            return $state;
        });
        Notification::make()->title('■ Sustabdyta')->send();
    }
}
