<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\Employee;
use App\Models\SalaryStructure;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
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
                TextColumn::make('personal_email')
                    ->label('Email')
                    ->placeholder('Not set')
                    ->searchable(),
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
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                // Salary is payroll's domain, not HR's, throughout this app
                // (SalaryStructureResource itself is payroll_accountant-only
                // — RolePermissionSeeder never grants it to hr_admin) — even
                // though hr_admin can see this list, the column is hidden
                // unless the viewer can also see salary structures.
                // SalaryStructure::currentFor() (the same effective-dated
                // lookup PayrollCalculationService uses) resolves per row,
                // same tradeoff JournalEntryResource's own computed columns
                // (debit_total, is_reversed) already accept in this app —
                // fine at employee-list scale, not worth a new eager-loaded
                // relation for a single table column.
                TextColumn::make('current_salary')
                    ->label('Salary')
                    ->state(fn (Employee $record): ?string => SalaryStructure::currentFor($record->id)?->basic_salary)
                    ->money('NPR')
                    ->weight(FontWeight::Medium)
                    // The panel's actual configured primary (Color::Blue,
                    // ConfiguresPanelDefaults) rather than a hardcoded hex or
                    // a semantic status color like success/danger — salary
                    // isn't a status, so borrowing green/red here would
                    // wrongly imply "good"/"bad" (DESIGN.md T8's "semantic,
                    // not decorative" principle, applied to a non-status
                    // figure worth visually distinguishing from plain text).
                    ->color('primary')
                    ->placeholder('Not set')
                    ->alignEnd()
                    ->visible(fn (): bool => auth()->user()?->can('ViewAny:SalaryStructure') ?? false),
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
