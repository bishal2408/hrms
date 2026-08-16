<?php

namespace App\Filament\Resources\PayrollRates\Pages;

use App\Filament\Resources\PayrollRates\PayrollRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayrollRates extends ManageRecords
{
    protected static string $resource = PayrollRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
