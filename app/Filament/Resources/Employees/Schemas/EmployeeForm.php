<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Filament\Forms\Components\NepaliDatePicker;
use App\Models\Employee;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * The employee record form.
 *
 * A page form, not a modal, so it uses the sectioned three-column shell
 * (DESIGN.md F1): a main stack spanning two columns and a placement sidebar
 * spanning one.
 */
class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Each column is its own Group. Without the wrappers, grid
                // auto-placement runs left-to-right across all five sections:
                // the sidebar column stays empty for the first two rows and
                // "Placement" only begins level with the third section.
                Group::make()
                    ->columnSpan(2)
                    ->schema(self::mainSections()),

                Group::make()
                    ->columnSpan(1)
                    ->schema(self::sidebarSections()),
            ]);
    }

    /** @return array<int, Section> */
    protected static function mainSections(): array
    {
        return [
            Section::make('Personal')
                ->description('Identity details as they appear on official documents.')
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('middle_name')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    NepaliDatePicker::make('date_of_birth')
                        ->label('Date of birth (BS)'),
                    Select::make('gender')
                        ->options(Employee::genderOptions())
                        ->native(false),
                    Select::make('marital_status')
                        ->options(Employee::maritalStatusOptions())
                        ->required()
                        ->native(false)
                        ->helperText('Determines which TDS slab table applies to this employee.'),
                ]),

            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('personal_email')
                        ->label('Personal email')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),
                    Textarea::make('address')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Statutory identity')
                ->description('Sensitive. Needed for TDS filing and statutory registers.')
                ->columns(2)
                ->schema([
                    TextInput::make('pan_number')
                        ->label('PAN')
                        ->maxLength(255)
                        ->helperText("The employee's own Permanent Account Number."),
                    TextInput::make('citizenship_number')
                        ->label('Citizenship number')
                        ->maxLength(255),
                ]),
        ];
    }

    /** @return array<int, Section> */
    protected static function sidebarSections(): array
    {
        return [
            Section::make('Placement')
                ->schema([
                    TextInput::make('employee_code')
                        ->label('Employee ID')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Unique staff identifier used across payroll and attendance.'),
                    Select::make('department_id')
                        ->label('Department')
                        ->relationship('department', 'name', fn (Builder $query) => $query->active())
                        ->searchable()
                        ->preload(),
                    Select::make('designation_id')
                        ->label('Job title')
                        ->relationship('designation', 'name', fn (Builder $query) => $query->active())
                        ->searchable()
                        ->preload(),
                    Select::make('manager_id')
                        ->label('Reports to')
                        ->relationship(
                            name: 'manager',
                            titleAttribute: 'first_name',
                            // Scoped like the resource itself: a relationship
                            // select runs its own query, so without this it would
                            // list every employee's name to someone who may only
                            // see their own reports.
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->active()
                                ->when(
                                    auth()->user() instanceof User,
                                    fn (Builder $scoped) => $scoped->visibleTo(auth()->user()),
                                ),
                            ignoreRecord: true,
                        )
                        ->getOptionLabelFromRecordUsing(fn (Employee $record): string => $record->full_name)
                        ->searchable(['first_name', 'last_name', 'employee_code'])
                        ->preload()
                        ->helperText('Drives leave approvals and who can see this record.'),
                    NepaliDatePicker::make('hired_at')
                        ->label('Hired on (BS)')
                        ->required(),
                    NepaliDatePicker::make('terminated_at')
                        ->label('Terminated on (BS)')
                        ->helperText('Leave empty while the employee is still with the company.'),
                ]),

            Section::make('Login')
                ->description('Optional — only needed for employee self-service.')
                ->schema([
                    Select::make('user_id')
                        ->label('Linked account')
                        ->relationship('user', 'email')
                        ->searchable()
                        ->preload()
                        ->helperText('Without an account this employee has a record but cannot sign in.'),
                ]),
        ];
    }
}
