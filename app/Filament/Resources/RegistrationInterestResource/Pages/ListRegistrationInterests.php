<?php

namespace App\Filament\Resources\RegistrationInterestResource\Pages;

use App\Filament\Resources\RegistrationInterestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRegistrationInterests extends ListRecords
{
    protected static string $resource = RegistrationInterestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Eksportuoti CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn() => route('admin.interests.export'))
                ->openUrlInNewTab(),
        ];
    }
}
