<?php

namespace App\Filament\Resources\SalaryStructures\Tables;

use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\SalaryStructure;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalaryStructuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->weight(FontWeight::Medium)
                    ->description(fn (SalaryStructure $record): ?string => $record->employee?->employee_code)
                    ->searchable(['employee.first_name', 'employee.last_name'])
                    ->sortable(),
                TextColumn::make('basic_salary')
                    ->money('NPR')
                    ->alignEnd()
                    ->sortable(),
                NepaliDateColumn::make('effective_from')
                    ->label('Effective from (BS)')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload(),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading('No salary structures yet')
            ->emptyStateDescription('Set an employee\'s basic salary and allowances so payroll has something to calculate.')
            // No delete: a structure is superseded by a newer one, never
            // removed — historical payroll must stay reproducible against
            // whichever structure was actually in effect at the time.
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
