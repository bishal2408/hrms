<?php

namespace App\Filament\Resources\SalaryStructures\Schemas;

use App\Filament\Forms\Components\NepaliDatePicker;
use App\Models\Employee;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One effective-dated version of an employee's pay (DESIGN.md F1 — a page
 * form, sectioned). A new SalaryStructure is how pay changes; there is no
 * "amend an old version" — see the SalaryStructure model docblock.
 */
class SalaryStructureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pay')
                    ->columns(2)
                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->relationship('employee', 'first_name', fn ($query) => $query->active())
                            ->getOptionLabelFromRecordUsing(fn (Employee $record): string => $record->full_name)
                            ->searchable(['first_name', 'last_name', 'employee_code'])
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('basic_salary')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('NPR'),
                        NepaliDatePicker::make('effective_from')
                            ->label('Effective from (BS)')
                            ->required()
                            ->helperText('Payroll runs use the structure that was effective on their pay date. Changing pay means adding a new structure, not editing this one.'),
                        TextInput::make('notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Allowances & deductions')
                    ->description('Flat amounts added to or subtracted from basic salary each pay period — unaffected by attendance.')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('salary_component_type_id')
                                    ->label('Component')
                                    ->relationship('componentType', 'name', fn ($query) => $query->active())
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(2),
                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('NPR'),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add component')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
