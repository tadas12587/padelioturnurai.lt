<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use App\Models\TournamentScore;
use App\Services\OverlayData;
use App\Services\ScoreEngine;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class H2hControlPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Akistata (H2H)';
    protected static ?string $title = 'Akistata (Head to Head)';
    protected static string $view = 'filament.pages.h2h-control';

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

    /** H2H windows of the selected overlay. @return array<string,string> */
    public function windowOptions(): array
    {
        $out = [];
        foreach ($this->selectedOverlay()?->windows ?? [] as $w) {
            if (($w['type'] ?? null) === 'h2h') {
                $out[$w['id']] = $w['name'] ?? $w['id'];
            }
        }

        return $out;
    }

    public function activeMatchId()
    {
        return $this->selectedOverlay()?->state['h2h_match_id'] ?? null;
    }

    /** Fixtures (matches) for the overlay's tournament, filtered by search. @return list<array<string,mixed>> */
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
            $hay = mb_strtolower(implode(' ', array_merge($m['team1'] ?? [], $m['team2'] ?? [])));

            return str_contains($hay, $needle);
        });

        return array_values(array_map(fn ($m) => [
            'id'    => $m['id'] ?? null,
            'team1' => $m['team1'] ?? [],
            'team2' => $m['team2'] ?? [],
            'time'  => $m['time'] ?? null,
            'date'  => $m['date'] ?? null,
            'court' => $m['court'] ?? null,
            'category' => $m['category'] ?? null,
            'in_progress' => ! empty($m['in_progress']),
        ], $rows));
    }

    public function showMatch($matchId): void
    {
        if (! $this->windowId) {
            Notification::make()->title('Pirma pasirink Akistatos langą.')->warning()->send();

            return;
        }
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['h2h_match_id'] = $matchId;
        $state = Overlay::showWindow($state, $this->windowId);
        $overlay->state = $state;
        $overlay->save();

        Notification::make()->title('▶ Rodoma')->success()->send();
    }

    public function stop(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state = $this->windowId ? Overlay::hideWindow($state, $this->windowId) : Overlay::hideAll($state);
        $overlay->state = $state;
        $overlay->save();

        Notification::make()->title('■ Sustabdyta')->send();
    }

    public function showScore(): bool
    {
        return (bool) ($this->selectedOverlay()?->state['h2h_show_score'] ?? false);
    }

    /** Toggle H2H centre between match info (time/court) and the live score. */
    public function toggleScore(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $on = ! ($state['h2h_show_score'] ?? false);
        $state['h2h_show_score'] = $on;

        if ($on) {
            $matchId = $state['h2h_match_id'] ?? null;
            if (! $matchId) {
                Notification::make()->title('Pirma pasirink akistatą (rungtynes).')->warning()->send();

                return;
            }
            // Auto-load this overlay's own score window with the same pair,
            // unless it is already on this match.
            $tid = (string) $overlay->tournament_external_id;
            $scoreWindow = collect($overlay->windows ?? [])->firstWhere('type', 'score') ?? [];
            $scoreWindowId = $scoreWindow['id'] ?? null;
            $sharedScore = TournamentScore::stateFor($tid, $scoreWindowId);
            $sameMatch = (string) (TournamentScore::matchFor($tid, $scoreWindowId) ?? '') === (string) $matchId;
            if (! $sameMatch || empty($sharedScore['teams'])) {
                $m = collect(app(OverlayData::class)->matches($tid))
                    ->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $matchId);
                if ($m) {
                    $engine = app(ScoreEngine::class);
                    $newScore = $engine->init($engine->config($scoreWindow), [$m['team1'] ?? [], $m['team2'] ?? []]);
                    TournamentScore::put($tid, $newScore, $matchId, $scoreWindowId);
                }
            }
        }

        $overlay->state = $state;
        $overlay->save();

        Notification::make()
            ->title($on ? '✔ Centre: rezultatas (0:0). Taškus vesk „Rezultatas" valdyme.' : 'Centre: laikas / kortas')
            ->success()
            ->send();
    }
}
