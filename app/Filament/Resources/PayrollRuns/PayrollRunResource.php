<?php

namespace App\Filament\Resources\PayrollRuns;

use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Filament\Resources\PayrollRuns\RelationManagers\PayslipsRelationManager;
use App\Filament\Resources\PayrollRuns\Tables\PayrollRunsTable;
use App\Models\PayrollRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * No form(): a run is never created or edited through a generic form — it's
 * created via the "Run Payroll" action (ListPayrollRuns) and transitions
 * through Recalculate/Finalize/Discard (ViewPayrollRun), both backed by
 * PayrollRunService. See that service for why nothing here writes to a
 * PayrollRun or Payslip directly.
 */
class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static ?string $slug = 'payroll/runs';

    protected static string|UnitEnum|null $navigationGroup = 'Payroll';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Payroll Runs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return PayrollRunsTable::configure($table);
    }

    /** @return Builder<PayrollRun> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('payslips');
    }

    public static function getRelations(): array
    {
        return [
            PayslipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollRuns::route('/'),
            'view' => ViewPayrollRun::route('/{record}'),
        ];
    }
}
