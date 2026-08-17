<?php

namespace App\Filament\Resources\SalaryComponentTypes;

use App\Filament\Resources\SalaryComponentTypes\Pages\ManageSalaryComponentTypes;
use App\Models\SalaryComponentType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class SalaryComponentTypeResource extends Resource
{
    protected static ?string $model = SalaryComponentType::class;

    protected static ?string $slug = 'setup/salary-components';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'Salary Components';

    public static function form(Schema $schema): Schema
    {
        // Modal form — no Section (DESIGN.md F2).
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Short unique identifier, e.g. transport.'),
                Select::make('component_type')
                    ->options(SalaryComponentType::componentTypeOptions())
                    ->required()
                    ->native(false)
                    ->helperText('Whether this line adds to or subtracts from pay.'),
                Toggle::make('is_active')
                    ->inline(false)
                    ->helperText('Inactive components stay on historical salary structures but cannot be added to new ones.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->description(fn (SalaryComponentType $record): string => $record->code)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('component_type')
                    ->badge()
                    ->color(fn (string $state): string => $state === SalaryComponentType::TYPE_ALLOWANCE ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => SalaryComponentType::componentTypeOptions()[$state] ?? $state),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('component_type')
                    ->options(SalaryComponentType::componentTypeOptions()),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All components')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedTag)
            ->emptyStateHeading('No salary components yet')
            ->emptyStateDescription('Add allowance and deduction types so they can be added to an employee\'s salary structure.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalaryComponentTypes::route('/'),
        ];
    }
}
