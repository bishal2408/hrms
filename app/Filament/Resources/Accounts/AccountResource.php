<?php

namespace App\Filament\Resources\Accounts;

use App\Filament\Resources\Accounts\Pages\ManageAccounts;
use App\Models\Account;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use UnitEnum;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $slug = 'accounting/accounts';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Chart of Accounts';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        // Modal form — no Section (DESIGN.md F2).
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('account_type')
                    ->label('Type')
                    ->options(Account::accountTypeOptions())
                    ->required()
                    ->native(false),
                Select::make('parent_account_id')
                    ->label('Parent account')
                    ->relationship(name: 'parent', titleAttribute: 'name', ignoreRecord: true)
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('Optional — groups this account under a broader one on reports.'),
                Toggle::make('is_active')
                    ->inline(false)
                    ->helperText('Inactive accounts are hidden from new journal entries but keep their history.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Account::accountTypeOptions()[$state] ?? $state),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->label('Type')
                    ->options(Account::accountTypeOptions()),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All accounts')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedListBullet)
            ->emptyStateHeading('No accounts yet')
            ->emptyStateDescription('Add accounts to build the chart of accounts before posting journal entries.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // journal_lines.account_id and accounts.parent_account_id
                    // are both restrictOnDelete() — the database already
                    // refuses to delete an account with ledger history or
                    // child accounts. Without this override that surfaces as
                    // a raw QueryException instead of a clean message.
                    // is_active is the real "remove this account" mechanism
                    // once it has history (see its own helper text above).
                    // successNotificationTitle(null): DeleteAction's default
                    // setUp() already wires an automatic success toast that
                    // fires whenever action() returns without throwing —
                    // since the catch block below deliberately doesn't
                    // rethrow, that default would fire alongside the manual
                    // notifications here on every call, success or blocked.
                    ->successNotificationTitle(null)
                    ->action(function (Account $record): void {
                        try {
                            $record->delete();

                            Notification::make()
                                ->title('Account deleted.')
                                ->success()
                                ->send();
                        } catch (QueryException) {
                            Notification::make()
                                ->title('Cannot delete this account')
                                ->body('It has journal entry history or child accounts. Turn off "Active" in Edit instead.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Accounts with journal entry history or child accounts cannot be deleted and will be skipped — deactivate them instead.'),
                ]),
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Account $record */
        return "{$record->code} — {$record->name}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAccounts::route('/'),
        ];
    }
}
