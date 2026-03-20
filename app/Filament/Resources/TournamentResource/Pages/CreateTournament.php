<?php

namespace App\Filament\Resources\TournamentResource\Pages;

use App\Filament\Resources\TournamentResource;
use App\Models\TournamentPhoto;
use App\Services\ImageService;
use Filament\Resources\Pages\CreateRecord;

class CreateTournament extends CreateRecord
{
    protected static string $resource = TournamentResource::class;

    protected function afterCreate(): void
    {
        $this->processBulkPhotos();
    }

    private function processBulkPhotos(): void
    {
        $state = $this->form->getRawState();
        $bulkPhotos = $state['bulk_photos'] ?? [];

        if (empty($bulkPhotos)) {
            return;
        }

        $sortOrder = $this->record->photos()->count();

        foreach ($bulkPhotos as $path) {
            ImageService::resizePublic($path);

            TournamentPhoto::create([
                'tournament_id' => $this->record->id,
                'path'          => $path,
                'sort_order'    => $sortOrder++,
            ]);
        }
    }
}
