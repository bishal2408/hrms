<?php

namespace App\Filament\Resources\LeaveTypes;

use App\Filament\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Models\LeaveType;
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

class LeaveTypeResource extends Resource
{
    protected static ?string $model = LeaveType::class;

    protected static ?string $slug = 'setup/leave-types';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        // No Section here: this form is only ever shown in a modal, which
        // already supplies the heading and the card (DESIGN.md F2).
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The name employees see when requesting leave.'),
                TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Short unique identifier, e.g. SICK.'),
                TextInput::make('default_entitlement_days')
                    ->label('Default entitlement')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('days per year')
                    ->helperText('Balances reset on the Nepali fiscal year boundary. Leave empty for types with no fixed allowance, such as leave without pay.'),
                Toggle::make('is_paid')
                    ->label('Paid leave')
                    // Label above the switch so it lines up with the input beside it.
                    ->inline(false)
                    ->helperText('Paid leave is included in salary; unpaid leave is deducted.')
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
                    ->description(fn (LeaveType $record): string => $record->code)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('default_entitlement_days')
                    ->label('Entitlement')
                    ->numeric()
                    ->suffix(' days')
                    ->placeholder('No fixed allowance')
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('is_paid')
                    ->label('Paid')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_paid')
                    ->label('Paid leave')
                    ->placeholder('All leave types')
                    ->trueLabel('Paid only')
                    ->falseLabel('Unpaid only'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('No leave types yet')
            ->emptyStateDescription('Add the leave types from the Labor Act 2074 so employees have something to request against.')
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
            'index' => ManageLeaveTypes::route('/'),
        ];
    }
}
