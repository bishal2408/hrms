<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $slug = 'people/departments';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = 20;

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
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->helperText('Short identifier used in reports and exports, e.g. FIN.'),
                Toggle::make('is_active')
                    ->inline(false)
                    ->helperText('Inactive departments stay on historical records but cannot be assigned to new employees.')
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
                    ->description(fn (Department $record): string => $record->code)
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All departments')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedRectangleGroup)
            ->emptyStateHeading('No departments yet')
            ->emptyStateDescription('Add your departments so employees can be grouped and managers can be given scoped access.')
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
            'index' => ManageDepartments::route('/'),
        ];
    }
}
