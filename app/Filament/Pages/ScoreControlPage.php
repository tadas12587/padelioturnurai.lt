<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use App\Models\TournamentScore;
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

    private ?Overlay $overlayCache = null;

    /** Memoised for the request — this is called many times per render
     *  (tid(), currentWindow(), matches(), isLive() all go through it), and
     *  Overlay carries potentially large JSON columns (windows, bracket_data). */
    public function selectedOverlay(): ?Overlay
    {
        if (! $this->overlayId) {
            return null;
        }
        if ($this->overlayCache === null || $this->overlayCache->id !== $this->overlayId) {
            $this->overlayCache = Overlay::find($this->overlayId);
        }

        return $this->overlayCache;
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

    /** The tournament this scoreboard belongs to. */
    private function tid(): string
    {
        return (string) ($this->selectedOverlay()?->tournament_external_id ?? '');
    }

    /** @return array<string,mixed> score state for the selected window */
    public function scoreState(): array
    {
        return TournamentScore::stateFor($this->tid(), $this->windowId);
    }

    public function activeMatchId()
    {
        return TournamentScore::matchFor($this->tid(), $this->windowId);
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
        $this->overlayCache = $overlay; // keep selectedOverlay()'s memoised copy in sync
    }

    /**
     * Mutate this window's score. Pass $matchId to also set it. One query for
     * the current row (state + match_id together) instead of two.
     */
    private function saveScore(callable $fn, string $matchId = '__keep__'): void
    {
        $tid = $this->tid();
        if ($tid === '' || ! $this->windowId) {
            return;
        }
        $both = TournamentScore::bothFor($tid, $this->windowId);
        $score = $fn($both['state']);
        if (empty($score)) {
            return; // nothing to persist (e.g. a point tapped before any match is loaded)
        }
        $mid = $matchId === '__keep__' ? $both['match_id'] : $matchId;
        TournamentScore::put($tid, $score, $mid, $this->windowId);
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
        // Load the (shared) score only — showing it is a separate step (play()
        // for the standalone window, or the Akistata centre toggle).
        $this->saveScore(fn () => $this->engine()->init($this->config(), $teams), (string) $matchId);
        Notification::make()->title('Rezultatas paruoštas')->success()->send();
    }

    public function play(): void
    {
        if (! $this->windowId) {
            Notification::make()->title('Pirma pasirink Rezultato langą.')->warning()->send();

            return;
        }
        $this->saveState(fn ($state) => Overlay::showWindow($state, $this->windowId));
        Notification::make()->title('▶ Rodomas rezultato langas')->success()->send();
    }

    public function isLive(): bool
    {
        return Overlay::isShown($this->selectedOverlay()?->state ?? [], (string) $this->windowId);
    }

    public function point(int $team): void
    {
        if (! $this->overlayId) {
            return;
        }
        $this->saveScore(fn ($score) => empty($score) ? $score : $this->engine()->point($this->config(), $score, $team));
    }

    public function game(int $team): void
    {
        if (! $this->overlayId) {
            return;
        }
        $this->saveScore(fn ($score) => empty($score) ? $score : $this->engine()->game($this->config(), $score, $team));
    }

    public function undo(): void
    {
        $this->saveScore(fn ($score) => $this->engine()->undo($this->config(), $score ?: []));
    }

    public function resetScore(): void
    {
        $this->saveScore(fn ($score) => $this->engine()->reset($this->config(), $score ?: []));
    }

    public function setServer(int $team): void
    {
        $this->saveScore(fn ($score) => $this->engine()->setServer($score ?: [], $team));
    }

    public function stop(): void
    {
        $this->saveState(fn ($state) => $this->windowId ? Overlay::hideWindow($state, $this->windowId) : Overlay::hideAll($state));
        Notification::make()->title('■ Sustabdyta')->send();
    }
}
