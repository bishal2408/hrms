<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Infolists\Components\NepaliDateEntry;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\PayrollRun;
use App\Services\PayrollRunService;
use Exception;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewPayrollRun extends ViewRecord
{
    protected static string $resource = PayrollRunResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                NepaliDateEntry::make('period_start')->label('From (BS)'),
                NepaliDateEntry::make('period_end')->label('To (BS)'),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PayrollRun::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => PayrollRun::statusColor($state)),
                TextEntry::make('createdBy.name')->label('Run by'),
                TextEntry::make('finalizedBy.name')->label('Finalized by')->placeholder('—'),
                TextEntry::make('finalized_at')->dateTime()->placeholder('—'),

                Section::make('Skipped employees')
                    ->description('Missing salary structure, PF/SSF rate or tax slab for this period — fix their setup and recalculate.')
                    ->columnSpanFull()
                    ->visible(fn (PayrollRun $record): bool => filled($record->skipped_employees))
                    ->schema([
                        RepeatableEntry::make('skipped_employees')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')->weight('medium'),
                                TextEntry::make('reason')->color('danger'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->recalculateAction(),
            $this->finalizeAction(),
            $this->discardAction(),
        ];
    }

    protected function recalculateAction(): Action
    {
        return Action::make('recalculate')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Every payslip in this draft will be discarded and recalculated from current data.')
            ->visible(fn (PayrollRun $record): bool => $record->isDraft() && (auth()->user()?->can('Update:PayrollRun') ?? false))
            ->action(function (PayrollRun $record): void {
                try {
                    app(PayrollRunService::class)->recalculate($record);
                    Notification::make()->title('Payroll recalculated.')->success()->send();
                } catch (Exception $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    protected function finalizeAction(): Action
    {
        return Action::make('finalize')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Finalizing locks every payslip in this run. Corrections afterward are made through adjustments, not by recalculating.')
            ->visible(fn (PayrollRun $record): bool => $record->isDraft() && (auth()->user()?->can('Update:PayrollRun') ?? false))
            ->action(function (PayrollRun $record): void {
                try {
                    app(PayrollRunService::class)->finalize($record, auth()->user());
                    Notification::make()->title('Payroll run finalized.')->success()->send();
                } catch (Exception $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    protected function discardAction(): Action
    {
        return Action::make('discard')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This draft and every payslip in it will be deleted. This cannot be undone.')
            ->visible(fn (PayrollRun $record): bool => $record->isDraft() && (auth()->user()?->can('Delete:PayrollRun') ?? false))
            ->action(function (PayrollRun $record): void {
                try {
                    app(PayrollRunService::class)->discard($record);
                    Notification::make()->title('Payroll run discarded.')->success()->send();
                    $this->redirect(PayrollRunResource::getUrl('index'));
                } catch (Exception $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
