<?php

namespace App\Filament\Resources\SalaryStructures;

use App\Filament\Resources\SalaryStructures\Pages\CreateSalaryStructure;
use App\Filament\Resources\SalaryStructures\Pages\EditSalaryStructure;
use App\Filament\Resources\SalaryStructures\Pages\ListSalaryStructures;
use App\Filament\Resources\SalaryStructures\Schemas\SalaryStructureForm;
use App\Filament\Resources\SalaryStructures\Tables\SalaryStructuresTable;
use App\Models\Employee;
use App\Models\SalaryStructure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * An employee's effective-dated pay: basic salary plus itemized
 * allowance/deduction lines. No delete anywhere in this resource — pay
 * history is superseded by a newer SalaryStructure, never edited or removed,
 * so a payroll run against a past period always sees what was actually in
 * effect then.
 */
class SalaryStructureResource extends Resource
{
    protected static ?string $model = SalaryStructure::class;

    protected static ?string $slug = 'payroll/salary-structures';

    protected static string|UnitEnum|null $navigationGroup = 'Payroll';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Salary Structures';

    public static function form(Schema $schema): Schema
    {
        return SalaryStructureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalaryStructuresTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['employee.first_name', 'employee.last_name', 'employee.employee_code'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var SalaryStructure $record */
        return $record->employee instanceof Employee ? $record->employee->full_name : "Salary Structure #{$record->id}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalaryStructures::route('/'),
            'create' => CreateSalaryStructure::route('/create'),
            'edit' => EditSalaryStructure::route('/{record}/edit'),
        ];
    }
}
