<?php

namespace App\Filament\Resources\OverlayResource\Pages;

use App\Filament\Resources\OverlayResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOverlay extends CreateRecord
{
    protected static string $resource = OverlayResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return OverlayResource::advanceBracketWindows($data);
    }
}
