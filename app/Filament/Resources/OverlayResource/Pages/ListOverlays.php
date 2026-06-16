<?php

namespace App\Filament\Resources\OverlayResource\Pages;

use App\Filament\Resources\OverlayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOverlays extends ListRecords
{
    protected static string $resource = OverlayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
