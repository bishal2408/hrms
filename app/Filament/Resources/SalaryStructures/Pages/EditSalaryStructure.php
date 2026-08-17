<?php

namespace App\Filament\Resources\SalaryStructures\Pages;

use App\Filament\Resources\SalaryStructures\SalaryStructureResource;
use Filament\Resources\Pages\EditRecord;

/**
 * No Delete/ForceDelete/Restore: a salary structure is superseded by a new
 * one, never removed — see SalaryStructureResource's docblock.
 */
class EditSalaryStructure extends EditRecord
{
    protected static string $resource = SalaryStructureResource::class;
}
