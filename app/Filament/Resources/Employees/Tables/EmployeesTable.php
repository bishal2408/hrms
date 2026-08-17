<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\Employee;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('first_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Employee')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Employee $record): string => $record->employee_code)
                    // full_name is composed in PHP, so searching and sorting
                    // have to target the underlying columns.
                    ->searchable(['first_name', 'middle_name', 'last_name', 'employee_code'])
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('designation.name')
                    ->label('Job title')
                    ->placeholder('Not set')
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('Not set')
                    ->sortable(),
                TextColumn::make('manager.full_name')
                    ->label('Reports to')
                    ->placeholder('No manager'),
                NepaliDateColumn::make('hired_at')
                    ->label('Hired (BS)')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('designation_id')
                    ->label('Job title')
                    ->relationship('designation', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('terminated_at')
                    ->label('Employment')
                    ->placeholder('Current employees')
                    ->trueLabel('Former employees')
                    ->falseLabel('All employees')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('terminated_at'),
                        false: fn ($query) => $query,
                        blank: fn ($query) => $query->whereNull('terminated_at'),
                    ),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('No employees yet')
            ->emptyStateDescription('Add your staff records to build the org chart that leave approvals and payroll depend on.')
            // No delete and no bulk delete: employment ends by setting a
            // termination date, which keeps the person on historical payroll,
            // attendance and leave records (DESIGN.md T9/T10).
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
