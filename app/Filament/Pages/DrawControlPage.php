<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use App\Services\DrawEngine;
use App\Services\OverlayData;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DrawControlPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Traukimo valdymas';
    protected static ?string $title = 'Traukimo valdymas';
    protected static string $view = 'filament.pages.draw-control';

    public ?int $overlayId = null;
    public ?string $windowId = null;
    public string $search = '';
    public ?string $manualSlot = null;

    public function selectedOverlay(): ?Overlay
    {
        return $this->overlayId ? Overlay::find($this->overlayId) : null;
    }

    /** @return array<int,string> */
    public function overlayOptions(): array
    {
        return Overlay::orderBy('name')->pluck('name', 'id')->all();
    }

    /** Draw-type windows of the selected overlay. @return array<string,string> */
    public function windowOptions(): array
    {
        $out = [];
        foreach ($this->selectedOverlay()?->windows ?? [] as $w) {
            if (($w['type'] ?? null) === 'draw') {
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
    public function drawState(): array
    {
        return $this->selectedOverlay()?->state['draws'][$this->windowId] ?? [];
    }

    private function saveDrawState(array $drawState): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['draws'][$this->windowId] = $drawState;
        $overlay->state = $state;
        $overlay->save();
    }

    public function loadParticipants(): void
    {
        $window = $this->currentWindow();
        if (! $window) {
            return;
        }
        $teams = app(OverlayData::class)->participants(
            (string) $this->selectedOverlay()->tournament_external_id,
            (int) ($window['category_id'] ?? 0),
        );
        $state = app(DrawEngine::class)->init($window, $teams);
        $this->saveDrawState($state);

        Notification::make()->title('Dalyviai užkrauti (' . count($teams) . ')')->success()->send();
    }

    private function run(callable $fn): void
    {
        $window = $this->currentWindow();
        if (! $window) {
            return;
        }
        try {
            $this->saveDrawState($fn(app(DrawEngine::class), $window, $this->drawState()));
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function drawNext(): void
    {
        $this->run(fn (DrawEngine $e, $w, $s) => $e->drawNext($w, $s));
    }

    public function placeManual(int $teamId): void
    {
        if (! $this->manualSlot) {
            Notification::make()->title('Pirma pasirink vietą.')->warning()->send();

            return;
        }
        $slot = $this->manualSlot;
        $this->run(fn (DrawEngine $e, $w, $s) => $e->place($w, $s, $teamId, $slot));
        $this->manualSlot = null;
    }

    public function undo(): void
    {
        $this->run(fn (DrawEngine $e, $w, $s) => $e->undo($w, $s));
    }

    public function resetBoard(): void
    {
        $this->run(fn (DrawEngine $e, $w, $s) => $e->reset($w, $s));
    }

    public function play(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['active_window_id'] = $this->windowId;
        $overlay->state = $state;
        $overlay->save();
        Notification::make()->title('▶ Rodoma')->success()->send();
    }

    public function stop(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['active_window_id'] = null;
        $overlay->state = $state;
        $overlay->save();
        Notification::make()->title('■ Sustabdyta')->send();
    }

    /** Remaining (unplaced) teams, filtered by search. @return list<array<string,mixed>> */
    public function remainingTeams(): array
    {
        $s = $this->drawState();
        $placed = array_values(array_filter($s['slots'] ?? [], fn ($t) => $t !== null));
        $teams = array_filter(
            $s['teams'] ?? [],
            fn ($t) => ! in_array($t['id'], $placed, true)
                && ($this->search === '' || stripos($t['name'] ?? '', $this->search) !== false),
        );

        return array_values($teams);
    }

    /** Empty slot keys for the manual-place picker. @return list<string> */
    public function emptySlots(): array
    {
        $s = $this->drawState();

        return array_values(array_keys(array_filter($s['slots'] ?? [], fn ($t) => $t === null)));
    }

    /** Team name for a placed slot value (for the mini board preview). */
    public function teamName($id): string
    {
        foreach ($this->drawState()['teams'] ?? [] as $t) {
            if (($t['id'] ?? null) === $id) {
                return $t['name'] ?? ('#' . $id);
            }
        }

        return '';
    }
}
