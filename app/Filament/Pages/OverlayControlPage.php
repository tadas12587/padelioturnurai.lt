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

    public function activeWindowId(): ?string
    {
        return $this->selectedOverlay()?->state['active_window_id'] ?? null;
    }

    public function play(string $windowId): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['active_window_id'] = $windowId;
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
