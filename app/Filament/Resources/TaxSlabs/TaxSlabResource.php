<?php

namespace App\Filament\Resources\TaxSlabs;

use App\Filament\Forms\Components\NepaliDatePicker;
use App\Filament\Resources\TaxSlabs\Pages\ManageTaxSlabs;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\TaxSlab;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TaxSlabResource extends Resource
{
    protected static ?string $model = TaxSlab::class;

    protected static ?string $slug = 'setup/tax-slabs';

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Tax Slabs';

    public static function form(Schema $schema): Schema
    {
        // No Sections here: this form is only ever shown in a modal, which
        // already supplies the heading and the card (DESIGN.md F2).
        return $schema
            ->components([
                Select::make('marital_status')
                    ->label('Applies to')
                    ->options(TaxSlab::maritalStatusOptions())
                    ->required()
                    ->native(false)
                    ->columnSpanFull()
                    ->helperText('One row is one bracket. A slab table is replaced as a whole, so every bracket of a version shares the same effective date.'),
                TextInput::make('lower_bound')
                    ->label('From (NPR)')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                TextInput::make('upper_bound')
                    ->label('To (NPR)')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Leave empty for the top bracket, which has no upper limit.'),
                TextInput::make('rate_percent')
                    ->label('Rate')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
                NepaliDatePicker::make('effective_from')
                    ->label('Effective from (BS)')
                    ->required()
                    ->helperText('Payroll runs use the slab table that was effective on their pay date, so historical payroll stays reproducible after a rate change.'),
                Textarea::make('notes')
                    ->helperText('Optional — e.g. the Finance Act or circular this bracket comes from.')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Newest table version first, then brackets in ascending order —
            // which is how a slab table is read.
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('marital_status')
                    ->label('Applies to')
                    ->formatStateUsing(fn (string $state): string => TaxSlab::maritalStatusOptions()[$state] ?? $state)
                    ->weight(FontWeight::Medium)
                    ->sortable(),
                TextColumn::make('lower_bound')
                    ->label('From')
                    ->money('NPR')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('upper_bound')
                    ->label('To')
                    ->money('NPR')
                    ->placeholder('No upper limit')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('rate_percent')
                    ->label('Rate')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->alignEnd()
                    ->sortable(),
                NepaliDateColumn::make('effective_from')
                    ->label('Effective from (BS)')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('marital_status')
                    ->label('Applies to')
                    ->options(TaxSlab::maritalStatusOptions()),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedScale)
            ->emptyStateHeading('No tax slabs yet')
            ->emptyStateDescription('Add the income tax brackets for single and married employees so TDS can be calculated.')
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
            'index' => ManageTaxSlabs::route('/'),
        ];
    }
}
