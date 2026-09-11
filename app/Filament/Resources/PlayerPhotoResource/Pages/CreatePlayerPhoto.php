<?php

namespace App\Filament\Resources\PlayerPhotoResource\Pages;

use App\Filament\Resources\PlayerPhotoResource;
use App\Services\OverlayData;
use Filament\Resources\Pages\CreateRecord;

class CreatePlayerPhoto extends CreateRecord
{
    protected static string $resource = PlayerPhotoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['person_key'] = app(OverlayData::class)->personKey($data['name'] ?? '');

        return $data;
    }
}
