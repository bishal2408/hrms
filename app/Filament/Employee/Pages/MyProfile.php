<?php

namespace App\Filament\Employee\Pages;

use App\Filament\Infolists\Components\NepaliDateEntry;
use App\Models\Employee;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Employee self-service: your own record, read only.
 *
 * Changes go through HR rather than being editable here — an employee editing
 * their own department, manager or hire date would let them alter the approval
 * chain and payroll inputs that depend on those fields.
 */
class MyProfile extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.employee.pages.my-profile';

    protected static ?string $slug = 'my-profile';

    protected static ?string $title = 'My profile';

    protected static ?string $navigationLabel = 'My profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 10;

    /**
     * The signed-in user's own employee record, or null when HR has not linked
     * one yet.
     */
    public function getEmployee(): ?Employee
    {
        return auth()->user()?->employee;
    }

    public function profileInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->getEmployee())
            ->columns(3)
            ->components([
                Section::make('You')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('full_name')
                            ->label('Name'),
                        TextEntry::make('employee_code')
                            ->label('Employee ID'),
                        NepaliDateEntry::make('date_of_birth')
                            ->label('Date of birth'),
                        TextEntry::make('marital_status')
                            ->label('Marital status')
                            ->formatStateUsing(fn (?string $state): ?string => $state === null
                                ? null
                                : (Employee::maritalStatusOptions()[$state] ?? $state)),
                    ]),

                Section::make('Your job')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('designation.name')
                            ->label('Job title')
                            ->placeholder('Not set'),
                        TextEntry::make('department.name')
                            ->label('Department')
                            ->placeholder('Not set'),
                        TextEntry::make('manager.full_name')
                            ->label('Reports to')
                            ->placeholder('Not set'),
                        NepaliDateEntry::make('hired_at')
                            ->label('Hired on'),
                    ]),

                Section::make('Contact details')
                    ->description('Ask HR to update these if anything has changed.')
                    ->columnSpan(3)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('personal_email')
                            ->label('Personal email')
                            ->placeholder('Not set'),
                        TextEntry::make('phone')
                            ->label('Phone')
                            ->placeholder('Not set'),
                        TextEntry::make('address')
                            ->label('Address')
                            ->placeholder('Not set'),
                    ]),
            ]);
    }
}
