<?php

namespace App\Filament\Resources\ProposalTierResource\Pages;

use App\Filament\Resources\ProposalTierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProposalTiers extends ListRecords
{
    protected static string $resource = ProposalTierResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
