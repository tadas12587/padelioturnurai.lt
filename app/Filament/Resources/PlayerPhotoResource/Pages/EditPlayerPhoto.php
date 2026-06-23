<?php

namespace App\Filament\Resources\PlayerPhotoResource\Pages;

use App\Filament\Resources\PlayerPhotoResource;
use App\Services\OverlayData;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlayerPhoto extends EditRecord
{
    protected static string $resource = PlayerPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['person_key'] = app(OverlayData::class)->personKey($data['name'] ?? '');

        return $data;
    }
}
