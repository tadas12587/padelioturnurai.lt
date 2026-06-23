<?php

namespace App\Filament\Resources\PlayerPhotoResource\Pages;

use App\Filament\Resources\PlayerPhotoResource;
use App\Models\Setting;
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

            Actions\Action::make('stockPhotos')
                ->label('Stock nuotraukos')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->fillForm(fn () => [
                    'male'   => Setting::get('h2h_stock_male'),
                    'female' => Setting::get('h2h_stock_female'),
                ])
                ->form([
                    Forms\Components\FileUpload::make('male')->label('Vyro stock (GIF/PNG)')
                        ->acceptedFileTypes(['image/gif', 'image/png', 'image/webp'])
                        ->disk('public')->directory('player-photos'),
                    Forms\Components\FileUpload::make('female')->label('Moters stock (GIF/PNG)')
                        ->acceptedFileTypes(['image/gif', 'image/png', 'image/webp'])
                        ->disk('public')->directory('player-photos'),
                ])
                ->action(function (array $data) {
                    Setting::set('h2h_stock_male', $data['male'] ?? null);
                    Setting::set('h2h_stock_female', $data['female'] ?? null);
                    Notification::make()->title('Stock nuotraukos išsaugotos')->success()->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
