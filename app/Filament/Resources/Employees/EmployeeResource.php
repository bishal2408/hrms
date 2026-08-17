<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Models\Employee;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $slug = 'people/employees';

    protected static ?string $recordTitleAttribute = 'employee_code';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    /**
     * Every listing, edit page and global search result goes through this
     * query, so scoping it is what actually enforces "managers see their
     * direct reports" — not the absence of a link (CLAUDE.md access control).
     *
     * @return Builder<Employee>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        return $user instanceof User ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    /**
     * Record binding is scoped the same way: without this, a manager could
     * open any employee's edit page by guessing the id.
     *
     * @return Builder<Employee>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $user = auth()->user();

        return $user instanceof User
            ? parent::getRecordRouteBindingEloquentQuery()->visibleTo($user)
            : parent::getRecordRouteBindingEloquentQuery()->whereRaw('1 = 0');
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'middle_name', 'last_name', 'employee_code'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Employee $record */
        return $record->full_name;
    }

    /**
     * A name alone is ambiguous in a company of any size, so results carry the
     * staff ID and where the person sits.
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Employee $record */
        return array_filter([
            'ID' => $record->employee_code,
            'Department' => $record->department?->name,
            'Job title' => $record->designation?->name,
        ]);
    }

    /** @return Builder<Employee> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery()->with(['department', 'designation']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
