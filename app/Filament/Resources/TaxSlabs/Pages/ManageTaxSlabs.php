<?php

namespace App\Filament\Resources\TaxSlabs\Pages;

use App\Filament\Resources\TaxSlabs\TaxSlabResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTaxSlabs extends ManageRecords
{
    protected static string $resource = TaxSlabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
