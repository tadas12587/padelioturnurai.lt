<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use App\Services\OverlayData;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OverlayControlPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-play';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Overlay valdymas';
    protected static ?string $title           = 'Overlay valdymas';
    protected static string  $view            = 'filament.pages.overlay-control';

    public ?int $overlayId = null;
    public ?array $data = [];

    public function mount(): void
    {
        $this->data = Overlay::defaultState();
    }

    public function updatedOverlayId(): void
    {
        $overlay = Overlay::find($this->overlayId);
        $this->data = array_merge(Overlay::defaultState(), $overlay?->state ?? []);
    }

    public function selectedOverlay(): ?Overlay
    {
        return $this->overlayId ? Overlay::find($this->overlayId) : null;
    }

    /** @return array<int,string> */
    public function overlayOptions(): array
    {
        return Overlay::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int,string> */
    public function categoryOptions(): array
    {
        $overlay = $this->selectedOverlay();
        if (! $overlay?->tournament_external_id) {
            return [];
        }
        $cats = app(OverlayData::class)->categories((string) $overlay->tournament_external_id);

        $out = [];
        foreach ($cats as $c) {
            $out[$c['id']] = $c['category']['name'] ?? ('#' . $c['id']);
        }
        return $out;
    }

    /** @return array<int|string,string> */
    public function groupOptions(): array
    {
        $overlay = $this->selectedOverlay();
        $catId   = $this->data['active_category_id'] ?? null;
        if (! $overlay?->tournament_external_id || ! $catId) {
            return ['' => 'Visi pogrupiai'];
        }
        $groups = app(OverlayData::class)->groups((string) $overlay->tournament_external_id, (int) $catId);

        $out = ['' => 'Visi pogrupiai'];
        foreach ($groups as $g) {
            $out[$g['id']] = $g['name'] ?? ('#' . $g['id']);
        }
        return $out;
    }

    public function apply(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);

        $overlay->state = array_merge(Overlay::defaultState(), $this->data, [
            'active_group_id' => ($this->data['active_group_id'] ?? '') ?: null,
            'visible'         => (bool) ($this->data['visible'] ?? false),
        ]);
        $overlay->save();

        Notification::make()->title('Pritaikyta!')->success()->send();
    }
}
