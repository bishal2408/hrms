<?php

namespace App\Filament\Resources\PayrollRuns\Tables;

use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\PayrollRun;
use App\Services\NepaliCalendar;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('period_start', 'desc')
            ->columns([
                NepaliDateColumn::make('period_start')
                    ->label('Period (BS)')
                    ->weight(FontWeight::Medium)
                    ->description(fn (PayrollRun $record): string => 'to '.NepaliCalendar::adToBs($record->period_end))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PayrollRun::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => PayrollRun::statusColor($state)),
                // Populated by PayrollRunResource::getEloquentQuery()'s
                // withCount('payslips') — ->counts() isn't available on
                // TextColumn in this Filament version, so the count is
                // eager-loaded as a plain attribute and displayed directly.
                TextColumn::make('payslips_count')
                    ->label('Employees paid')
                    ->alignEnd(),
                TextColumn::make('createdBy.name')
                    ->label('Run by'),
                TextColumn::make('finalized_at')
                    ->dateTime()
                    ->placeholder('Not finalized')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PayrollRun::statusOptions()),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedCalculator)
            ->emptyStateHeading('No payroll runs yet')
            ->emptyStateDescription('Run payroll for a period to calculate everyone\'s pay.')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
