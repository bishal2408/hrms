<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Login accounts.
 *
 * Separate from Employee on purpose: not every employee has a login, and not
 * every login is an employee (the super admin, for one). The link between the
 * two is set on the employee record, so there is a single write path for it —
 * here it is shown read only.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'administration/users';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        // Modal form — no Section (DESIGN.md F2).
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Used to sign in.'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    // Required when creating; on edit an empty box means "leave
                    // the current password alone" rather than "blank it".
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? 'The account holder should change this after their first sign-in.'
                        : 'Leave empty to keep the current password.')
                    ->columnSpanFull(),
                Select::make('roles')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => static::assignableRoles($query),
                    )
                    ->multiple()
                    ->preload()
                    ->native(false)
                    ->columnSpanFull()
                    ->helperText('Roles decide which panel and which screens this account can reach. Leave empty for a self-service-only account.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->placeholder('No roles'),
                TextColumn::make('employee.employee_code')
                    ->label('Employee record')
                    ->placeholder('Not linked')
                    ->url(fn (User $record): ?string => $record->employee === null
                        ? null
                        : EmployeeResource::getUrl('edit', ['record' => $record->employee])),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->label('Role'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedUserCircle)
            ->emptyStateHeading('No accounts yet')
            ->emptyStateDescription('Create accounts so staff can sign in — employees need one to reach self-service.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // Locking yourself out mid-session is not a recoverable
                    // mistake from inside the app.
                    ->hidden(fn (User $record): bool => $record->is(auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'employee']);
    }

    /**
     * Restrict which roles the signed-in user may hand out.
     *
     * A non-super-admin who can edit accounts must not be able to grant
     * `super_admin` — that would turn "manage users" into a route to full
     * control of the system, since super_admin is a gate-level bypass.
     *
     * Kept as a named method rather than an inline closure so the rule can be
     * asserted directly in tests.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public static function assignableRoles(Builder $query): Builder
    {
        return auth()->user()?->hasRole('super_admin') === true
            ? $query
            : $query->where('name', '!=', 'super_admin');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
