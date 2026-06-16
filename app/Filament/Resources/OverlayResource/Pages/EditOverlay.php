<?php

namespace App\Filament\Resources\OverlayResource\Pages;

use App\Filament\Resources\OverlayResource;
use App\Services\TournatedClient;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;

class EditOverlay extends EditRecord
{
    protected static string $resource = OverlayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fetchData')
                ->label('Nuskaityti turnyro duomenis')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => ($this->data['type'] ?? $this->record->type) === 'group_standings')
                ->modalHeading('Tournated turnyro duomenys')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Uždaryti')
                ->modalContent(function (): View {
                    $id = $this->data['tournament_external_id'] ?? $this->record->tournament_external_id;

                    $info = $id ? app(TournatedClient::class)->tournament((int) $id) : [];

                    return view('filament.overlay-fetch-preview', [
                        'info' => $info,
                        'id'   => $id,
                    ]);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
