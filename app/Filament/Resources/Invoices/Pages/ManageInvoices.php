<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Exceptions\MissingAccountingConfigurationException;
use App\Exceptions\MissingVatRateException;
use App\Filament\Forms\Components\NepaliDatePicker;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Customer;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ManageInvoices extends ManageRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->createInvoiceAction(),
        ];
    }

    protected function createInvoiceAction(): Action
    {
        return Action::make('createInvoice')
            ->label('New invoice')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->visible(fn (): bool => auth()->user()?->can('Create:Invoice') ?? false)
            ->schema([
                Select::make('customer_id')
                    ->label('Customer')
                    ->options(fn () => Customer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                NepaliDatePicker::make('issue_date')
                    ->label('Issue date (BS)')
                    ->required()
                    ->default(now()->toDateString()),
                Repeater::make('lines')
                    ->label('Line items')
                    ->schema([
                        TextInput::make('description')
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(0.01)
                            ->default(1)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Checkbox::make('is_vatable')
                            ->label('VAT applies')
                            ->default(true),
                    ])
                    ->columns(5)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Add line')
                    ->required(),
                Textarea::make('notes')
                    ->maxLength(1000),
            ])
            ->action(function (array $data): void {
                try {
                    app(InvoiceService::class)->create(
                        customer: Customer::findOrFail($data['customer_id']),
                        issueDate: Carbon::parse($data['issue_date']),
                        lines: $data['lines'],
                        createdBy: auth()->user(),
                        notes: $data['notes'] ?? null,
                    );

                    Notification::make()->title('Invoice issued.')->success()->send();
                } catch (MissingVatRateException|MissingAccountingConfigurationException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
