<?php

namespace App\Filament\Resources\SalaryComponentTypes\Pages;

use App\Filament\Resources\SalaryComponentTypes\SalaryComponentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalaryComponentTypes extends ManageRecords
{
    protected static string $resource = SalaryComponentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
