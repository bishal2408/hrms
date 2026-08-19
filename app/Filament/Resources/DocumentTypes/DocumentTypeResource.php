<?php

namespace App\Filament\Resources\DocumentTypes;

use App\Filament\Resources\DocumentTypes\Pages\ManageDocumentTypes;
use App\Models\DocumentType;
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

class DocumentTypeResource extends Resource
{
    protected static ?string $model = DocumentType::class;

    protected static ?string $slug = 'setup/document-types';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 45;

    protected static ?string $navigationLabel = 'Document Types';

    public static function form(Schema $schema): Schema
    {
        // Modal form — no Section (DESIGN.md F2).
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->inline(false)
                    ->helperText('Inactive types stay on already-uploaded documents but cannot be chosen for new ones.')
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
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All types')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('No document types yet')
            ->emptyStateDescription('Add categories like Employment Contract or Citizenship Copy so they can be attached to an employee\'s documents.')
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
            'index' => ManageDocumentTypes::route('/'),
        ];
    }
}
