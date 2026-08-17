<?php

namespace App\Filament\Employee\Resources\Payslips;

use App\Filament\Employee\Resources\Payslips\Pages\ManagePayslips;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Self-service, read only: your own payslips, once finalized. A draft
 * payslip's numbers can still change on recalculation — see
 * PayrollRunService — so it never appears here, only after the run locks.
 */
class PayslipResource extends Resource
{
    protected static ?string $model = Payslip::class;

    protected static ?string $slug = 'payslips';

    protected static string|UnitEnum|null $navigationGroup = 'Payroll';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payslips';

    protected static ?int $navigationSort = 10;

    /** @return Builder<Payslip> */
    public static function getEloquentQuery(): Builder
    {
        $employee = auth()->user()?->employee;

        $query = parent::getEloquentQuery()->whereHas('payrollRun', fn (Builder $q) => $q->where('status', PayrollRun::STATUS_FINALIZED));

        return $employee instanceof Employee
            ? $query->where('employee_id', $employee->id)
            : $query->whereRaw('1 = 0');
    }

    /** @return Builder<Payslip> */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payrollRun', 'adjustments']))
            ->columns([
                NepaliDateColumn::make('payrollRun.period_start')
                    ->label('Period (BS)'),
                TextColumn::make('gross_pay')->money('NPR')->alignEnd(),
                TextColumn::make('adjusted_net_pay')
                    ->label('Net pay')
                    ->money('NPR')
                    ->weight(FontWeight::Medium)
                    ->alignEnd(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading('No payslips yet')
            ->emptyStateDescription('Your payslips appear here once a payroll run including you has been finalized.')
            ->recordActions([
                static::viewAction(),
                static::downloadPdfAction(),
            ]);
    }

    protected static function viewAction(): Action
    {
        return Action::make('view')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading('Your payslip')
            ->infolist([
                Section::make('Pay')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('basic_after_attendance')->label('Basic')->money('NPR'),
                        TextEntry::make('allowances_total')->label('Allowances')->money('NPR'),
                        TextEntry::make('gross_pay')->money('NPR')->weight(FontWeight::Bold),
                    ]),
                Section::make('Deductions')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('pf_employee')->label('PF')->money('NPR'),
                        TextEntry::make('ssf_employee')->label('SSF')->money('NPR'),
                        TextEntry::make('tds')->label('TDS')->money('NPR'),
                    ]),
                Section::make('Allowance lines')
                    ->visible(fn (Payslip $record): bool => filled($record->allowance_items))
                    ->schema([
                        RepeatableEntry::make('allowance_items')
                            ->hiddenLabel()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('amount')->money('NPR'),
                            ]),
                    ]),
                Section::make('Adjustments')
                    ->visible(fn (Payslip $record): bool => $record->adjustments->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('adjustments')
                            ->hiddenLabel()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('reason'),
                                TextEntry::make('amount')->money('NPR'),
                            ]),
                    ]),
                TextEntry::make('adjusted_net_pay')
                    ->label('Net pay')
                    ->money('NPR')
                    ->weight(FontWeight::Bold)
                    ->size('lg'),
            ]);
    }

    protected static function downloadPdfAction(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(fn (Payslip $record): string => route('payslips.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayslips::route('/'),
        ];
    }
}
