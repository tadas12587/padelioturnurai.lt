<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use App\Services\DrawEngine;
use App\Services\OverlayData;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

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
    public ?string $selectedSlot = null;
    public string $newTeamName = '';

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

    /** Click a board slot: free → open the picker; occupied → free it. */
    public function selectSlot(string $slot): void
    {
        $s = $this->drawState();
        if (($s['slots'][$slot] ?? null) !== null) {
            $this->removeFromSlot($slot);

            return;
        }
        $this->selectedSlot = $slot;
        $this->search = '';
    }

    public function cancelSelect(): void
    {
        $this->selectedSlot = null;
        $this->search = '';
    }

    /** Place a team (id) or a BYE into the currently selected slot. */
    public function placeTeam(string $teamId): void
    {
        if (! $this->selectedSlot) {
            Notification::make()->title('Pirma spustelėk laisvą vietą lentoje.')->warning()->send();

            return;
        }
        $slot = $this->selectedSlot;
        $this->run(fn (DrawEngine $e, $w, $s) => $e->place($w, $s, $teamId, $slot));
        $this->selectedSlot = null;
        $this->search = '';
    }

    public function placeBye(): void
    {
        $this->placeTeam(DrawEngine::BYE);
    }

    /** Free a single slot (return its team to the pool). */
    public function removeFromSlot(string $slot): void
    {
        $window = $this->currentWindow();
        if (! $window) {
            return;
        }
        $state = $this->drawState();
        if (! array_key_exists($slot, $state['slots'] ?? [])) {
            return;
        }
        $state['slots'][$slot] = null;
        $state['current'] = null;
        $state['status'] = 'idle';
        $this->saveDrawState($state);
    }

    // ── Editable player pool ────────────────────────────────────
    public function addTeam(): void
    {
        $name = trim($this->newTeamName);
        if ($name === '') {
            return;
        }
        $state = $this->drawState();
        $state['teams'][] = ['id' => 'm' . Str::random(6), 'name' => $name, 'seed' => null, 'pot' => null];
        $this->saveDrawState($state);
        $this->newTeamName = '';
    }

    public function renameTeam(string $id, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $state = $this->drawState();
        foreach ($state['teams'] as &$t) {
            if ((string) $t['id'] === $id) {
                $t['name'] = $name;
            }
        }
        unset($t);
        $this->saveDrawState($state);
    }

    public function removeTeam(string $id): void
    {
        $state = $this->drawState();
        $state['teams'] = array_values(array_filter(
            $state['teams'] ?? [],
            fn ($t) => (string) $t['id'] !== $id,
        ));
        foreach ($state['slots'] ?? [] as $k => $tid) {
            if ((string) $tid === $id) {
                $state['slots'][$k] = null;
            }
        }
        $this->saveDrawState($state);
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

    /** All teams in the pool (for the editable list). @return list<array<string,mixed>> */
    public function allTeams(): array
    {
        return array_values($this->drawState()['teams'] ?? []);
    }

    /** The board layout (groups or bracket pairs) for the clickable preview. */
    public function layout(): array
    {
        $window = $this->currentWindow();

        return $window ? app(DrawEngine::class)->layout($window) : [];
    }

    /** Team name for a placed slot value (for the board preview). */
    public function teamName($id): string
    {
        if ($id === DrawEngine::BYE) {
            return 'BYE';
        }
        foreach ($this->drawState()['teams'] ?? [] as $t) {
            if ((string) ($t['id'] ?? '') === (string) $id) {
                return $t['name'] ?? ('#' . $id);
            }
        }

        return '';
    }
}
