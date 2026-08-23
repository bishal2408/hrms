<?php

namespace App\Filament\Resources\VatRates;

use App\Filament\Forms\Components\NepaliDatePicker;
use App\Filament\Resources\VatRates\Pages\ManageVatRates;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\VatRate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class VatRateResource extends Resource
{
    protected static ?string $model = VatRate::class;

    protected static ?string $slug = 'accounting/vat-rates';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'VAT Rates';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        // Modal form — no Section (DESIGN.md F2).
        return $schema
            ->components([
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
                    ->helperText('Invoices use the rate effective on their issue date, so changing this never rewrites an already-issued invoice. Add a new rate instead of editing an old one.'),
                Textarea::make('notes')
                    ->helperText('Optional — e.g. the notice this rate comes from.')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('rate_percent')
                    ->label('Rate')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->weight(FontWeight::Medium)
                    ->alignEnd()
                    ->sortable(),
                NepaliDateColumn::make('effective_from')
                    ->label('Effective from (BS)')
                    ->sortable(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent)
            ->emptyStateHeading('No VAT rate configured yet')
            ->emptyStateDescription('Add the current VAT rate so invoices with a VAT-applicable line can be issued.')
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
            'index' => ManageVatRates::route('/'),
        ];
    }
}
