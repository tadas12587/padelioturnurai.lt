<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OverlayControlPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-play';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Overlay valdymas';
    protected static ?string $title           = 'Overlay valdymas';
    protected static string  $view            = 'filament.pages.overlay-control';

    public ?int $overlayId = null;

    public function selectedOverlay(): ?Overlay
    {
        return $this->overlayId ? Overlay::find($this->overlayId) : null;
    }

    /** @return array<int,string> */
    public function overlayOptions(): array
    {
        return Overlay::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return list<string> */
    public function activeWindowIds(): array
    {
        return Overlay::activeIds($this->selectedOverlay()?->state ?? []);
    }

    public function isShown(string $id): bool
    {
        return in_array($id, $this->activeWindowIds(), true);
    }

    private function mutate(callable $fn): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $overlay->state = $fn(array_merge(Overlay::defaultState(), $overlay->state ?? []));
        $overlay->save();
    }

    public function play(string $windowId): void
    {
        $this->mutate(fn ($state) => Overlay::showWindow($state, $windowId));
        Notification::make()->title('▶ Rodoma')->success()->send();
    }

    public function hide(string $windowId): void
    {
        $this->mutate(fn ($state) => Overlay::hideWindow($state, $windowId));
        Notification::make()->title('■ Paslėpta')->send();
    }

    public function stop(): void
    {
        $this->mutate(fn ($state) => Overlay::hideAll($state));
        Notification::make()->title('■ Sustabdyta viskas')->send();
    }

    public function setNextMatch(string $text): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['next_match'] = $text;
        $overlay->state = $state;
        $overlay->save();

        Notification::make()->title('Atnaujinta')->success()->send();
    }
}
