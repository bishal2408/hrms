<?php

namespace App\Filament\Resources\Designations;

use App\Filament\Resources\Designations\Pages\ManageDesignations;
use App\Models\Designation;
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

class DesignationResource extends Resource
{
    protected static ?string $model = Designation::class;

    protected static ?string $slug = 'people/designations';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Designations';

    public static function form(Schema $schema): Schema
    {
        // Modal form — no Section (DESIGN.md F2).
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Job title')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull()
                    ->helperText('Kept as a shared list so titles stay consistent and reportable, e.g. Senior Accountant.'),
                Toggle::make('is_active')
                    ->inline(false)
                    ->helperText('Inactive titles stay on historical records but cannot be assigned to new employees.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Job title')
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All job titles')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedIdentification)
            ->emptyStateHeading('No job titles yet')
            ->emptyStateDescription('Add the job titles used in your company so employee records stay consistent.')
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
            'index' => ManageDesignations::route('/'),
        ];
    }
}
