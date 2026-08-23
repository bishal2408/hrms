<?php

namespace App\Filament\Resources\Invoices;

use App\Exceptions\InvoiceAlreadyCancelledException;
use App\Filament\Infolists\Components\NepaliDateEntry;
use App\Filament\Resources\Invoices\Pages\ManageInvoices;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\Invoice;
use App\Services\InvoiceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * No edit/delete action anywhere: an issued invoice is immutable
 * (CLAUDE.md — cancel-don't-delete on posted financial records). Correcting
 * a mistake means cancelling (InvoiceService::cancel(), which reverses the
 * GL posting) and issuing a fresh invoice, never touching the original.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $slug = 'accounting/invoices';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'createdBy']))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->weight(FontWeight::Medium)
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                NepaliDateColumn::make('issue_date')
                    ->label('Issued (BS)'),
                TextColumn::make('total')
                    ->money('NPR')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => Invoice::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => Invoice::statusOptions()[$state] ?? $state),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Invoice::statusOptions()),
                SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedDocumentCurrencyDollar)
            ->emptyStateHeading('No invoices yet')
            ->emptyStateDescription('Issue the first sales invoice to a customer.')
            ->recordActions([
                static::viewAction(),
                static::downloadPdfAction(),
                static::cancelAction(),
            ]);
    }

    protected static function viewAction(): Action
    {
        return Action::make('view')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (Invoice $record): string => $record->invoice_number)
            ->infolist([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer.name')->label('Customer'),
                        NepaliDateEntry::make('issue_date')->label('Issued (BS)'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => Invoice::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => Invoice::statusOptions()[$state] ?? $state),
                    ]),
                RepeatableEntry::make('lineItems')
                    ->hiddenLabel()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('description')->columnSpan(2),
                        TextEntry::make('quantity')->numeric(decimalPlaces: 2),
                        TextEntry::make('amount')->money('NPR'),
                    ]),
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')->money('NPR'),
                        TextEntry::make('vat_amount')->label('VAT')->money('NPR'),
                        TextEntry::make('total')->money('NPR')->weight(FontWeight::Bold),
                    ]),
                Section::make('Cancellation')
                    ->visible(fn (Invoice $record): bool => $record->isCancelled())
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cancelledBy.name')->label('Cancelled by')->placeholder('—'),
                        TextEntry::make('cancelled_at')->label('Cancelled at')->dateTime(),
                        TextEntry::make('cancellation_reason')->label('Reason')->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function downloadPdfAction(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(fn (Invoice $record): string => route('invoices.pdf', $record))
            ->openUrlInNewTab();
    }

    protected static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This reverses the invoice\'s ledger posting. The invoice itself stays on record for the audit trail, not edited or removed.')
            ->visible(fn (Invoice $record): bool => (! $record->isCancelled())
                && (auth()->user()?->can('Delete:Invoice') ?? false))
            ->schema([
                Textarea::make('reason')
                    ->required()
                    ->helperText('Shown on the invoice\'s cancellation record.'),
            ])
            ->action(function (Invoice $record, array $data): void {
                try {
                    app(InvoiceService::class)->cancel($record, auth()->user(), $data['reason']);

                    Notification::make()->title('Invoice cancelled.')->success()->send();
                } catch (InvoiceAlreadyCancelledException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInvoices::route('/'),
        ];
    }
}
