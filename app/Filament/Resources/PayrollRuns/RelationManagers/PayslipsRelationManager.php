<?php

namespace App\Filament\Resources\PayrollRuns\RelationManagers;

use App\Models\Payslip;
use App\Services\PayrollRunService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * No create/edit/delete/associate anywhere here: a Payslip only ever exists
 * as PayrollRunService's frozen output. This relation manager is read plus
 * two actions — View and Download PDF always, Add Adjustment only once the
 * run is finalized.
 */
class PayslipsRelationManager extends RelationManager
{
    protected static string $relationship = 'payslips';

    protected static ?string $title = 'Payslips';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee', 'adjustments']))
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Payslip $record): ?string => $record->employee?->employee_code),
                TextColumn::make('gross_pay')->money('NPR')->alignEnd(),
                TextColumn::make('net_pay')->label('Net pay')->money('NPR')->alignEnd(),
                // Always shown, not conditionally: it equals net_pay when
                // there are no adjustments, which is accurate on its own —
                // and ->visible() on a column is a whole-column toggle
                // evaluated without a row context, not a per-row conditional
                // (see DESIGN.md; the same mistake broke a column earlier
                // this project for the identical reason).
                TextColumn::make('adjusted_net_pay')
                    ->label('Adjusted net pay')
                    ->money('NPR')
                    ->alignEnd(),
            ])
            ->recordActions([
                $this->viewAction(),
                $this->downloadPdfAction(),
                $this->addAdjustmentAction(),
            ]);
    }

    protected function viewAction(): Action
    {
        return Action::make('view')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (Payslip $record): string => "Payslip — {$record->employee?->full_name}")
            ->infolist([
                Section::make('Pay')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('basic_after_attendance')->label('Basic (after attendance)')->money('NPR'),
                        TextEntry::make('allowances_total')->money('NPR'),
                        TextEntry::make('gross_pay')->money('NPR')->weight(FontWeight::Bold),
                    ]),
                Section::make('Statutory deductions')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('pf_employee')->label('PF (employee)')->money('NPR'),
                        TextEntry::make('ssf_employee')->label('SSF (employee)')->money('NPR'),
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
                Section::make('Deduction lines')
                    ->visible(fn (Payslip $record): bool => filled($record->deduction_items))
                    ->schema([
                        RepeatableEntry::make('deduction_items')
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

    protected function downloadPdfAction(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(fn (Payslip $record): string => route('payslips.pdf', $record))
            ->openUrlInNewTab();
    }

    protected function addAdjustmentAction(): Action
    {
        return Action::make('addAdjustment')
            ->label('Add adjustment')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('warning')
            ->visible(fn (): bool => $this->getOwnerRecord()->isFinalized() && (auth()->user()?->can('Update:PayrollRun') ?? false))
            ->schema([
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->helperText('Positive adds to net pay, negative deducts from it.'),
                Textarea::make('reason')
                    ->required()
                    ->helperText('Shown on the payslip PDF.'),
            ])
            ->action(function (Payslip $record, array $data): void {
                try {
                    app(PayrollRunService::class)->addAdjustment(
                        $record,
                        (float) $data['amount'],
                        $data['reason'],
                        auth()->user(),
                    );

                    Notification::make()->title('Adjustment recorded.')->success()->send();
                } catch (Exception $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
