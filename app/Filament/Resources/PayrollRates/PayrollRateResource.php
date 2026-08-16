<?php

namespace App\Filament\Resources\PayrollRates;

use App\Filament\Forms\Components\NepaliDatePicker;
use App\Filament\Resources\PayrollRates\Pages\ManagePayrollRates;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\PayrollRate;
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

class PayrollRateResource extends Resource
{
    protected static ?string $model = PayrollRate::class;

    protected static ?string $slug = 'setup/payroll-rates';

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'PF & SSF Rates';

    public static function form(Schema $schema): Schema
    {
        // No Sections here: this form is only ever shown in a modal, which
        // already supplies the heading and the card (DESIGN.md F2).
        return $schema
            ->components([
                Select::make('type')
                    ->label('Fund')
                    ->options(PayrollRate::typeOptions())
                    ->required()
                    ->native(false)
                    ->columnSpanFull(),
                TextInput::make('employee_contribution_percent')
                    ->label('Employee share')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
                TextInput::make('employer_contribution_percent')
                    ->label('Employer share')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
                NepaliDatePicker::make('effective_from')
                    ->label('Effective from (BS)')
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Payroll runs use the rate that was effective on their pay date, so changing a rate never rewrites past payroll. Add a new rate instead of editing an old one.'),
                Textarea::make('notes')
                    ->helperText('Optional — e.g. the circular or gazette this rate comes from.')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Fund')
                    ->formatStateUsing(fn (string $state): string => PayrollRate::typeOptions()[$state] ?? $state)
                    ->weight(FontWeight::Medium)
                    ->sortable(),
                TextColumn::make('employee_contribution_percent')
                    ->label('Employee')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('employer_contribution_percent')
                    ->label('Employer')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->alignEnd()
                    ->sortable(),
                NepaliDateColumn::make('effective_from')
                    ->label('Effective from (BS)')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Fund')
                    ->options(PayrollRate::typeOptions()),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent)
            ->emptyStateHeading('No contribution rates yet')
            ->emptyStateDescription('Add the current PF and SSF rates so payroll runs have something to calculate against.')
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
            'index' => ManagePayrollRates::route('/'),
        ];
    }
}
