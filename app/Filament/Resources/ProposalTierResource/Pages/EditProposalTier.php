<?php

namespace App\Filament\Resources\ProposalTierResource\Pages;

use App\Filament\Resources\ProposalTierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProposalTier extends EditRecord
{
    protected static string $resource = ProposalTierResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
