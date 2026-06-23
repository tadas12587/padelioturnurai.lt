<?php

namespace App\Filament\Resources\PlayerPhotoResource\Pages;

use App\Filament\Resources\PlayerPhotoResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPlayerPhotos extends ListRecords
{
    protected static string $resource = PlayerPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('loadPeople')
                ->label('Užkrauti dalyvius')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('tid')
                        ->label('Turnyras')
                        ->options(fn () => PlayerPhotoResource::tournamentOptions())
                        ->searchable()->required(),
                ])
                ->action(function (array $data) {
                    $n = PlayerPhotoResource::loadPeople($data['tid']);
                    Notification::make()->title("Užkrauta žmonių: {$n}")->success()->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
