<?php

namespace App\Filament\Resources\JournalEntries;

use App\Exceptions\JournalEntryAlreadyReversedException;
use App\Filament\Forms\Components\NepaliDatePicker;
use App\Filament\Infolists\Components\NepaliDateEntry;
use App\Filament\Resources\JournalEntries\Pages\ManageJournalEntries;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * No edit/delete action anywhere: a posted journal entry is immutable
 * (CLAUDE.md — no hard deletes on posted financial records). Correcting a
 * mistake means posting a reversal (JournalEntryService::reverse()), never
 * touching the original.
 */
class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $slug = 'accounting/journal-entries';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Journal Entries';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('entry_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['lines', 'postedBy']))
            ->columns([
                TextColumn::make('id')->label('#'),
                NepaliDateColumn::make('entry_date')
                    ->label('Date (BS)'),
                TextColumn::make('description')
                    ->weight(FontWeight::Medium)
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('debit_total')
                    ->label('Amount')
                    ->state(fn (JournalEntry $record): float => (float) $record->lines->sum('debit'))
                    ->money('NPR')
                    ->alignEnd(),
                TextColumn::make('postedBy.name')
                    ->label('Posted by')
                    ->placeholder('—'),
                IconColumn::make('is_reversed')
                    ->label('Reversed')
                    ->state(fn (JournalEntry $record): bool => $record->isReversed())
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('account')
                    ->label('Account')
                    ->options(fn () => Account::query()->orderBy('code')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $accountId) => $q->whereHas('lines', fn (Builder $lq) => $lq->where('account_id', $accountId)),
                    )),
                Filter::make('date')
                    ->schema([
                        NepaliDatePicker::make('from')->label('From (BS)'),
                        NepaliDatePicker::make('until')->label('Until (BS)'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('entry_date', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('entry_date', '<=', $until))),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedBookOpen)
            ->emptyStateHeading('No journal entries yet')
            ->emptyStateDescription('Post a manual entry, or an entry appears automatically once invoicing (Phase 4b) posts to the ledger.')
            ->recordActions([
                static::viewAction(),
                static::reverseAction(),
            ]);
    }

    protected static function viewAction(): Action
    {
        return Action::make('view')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (JournalEntry $record): string => "Journal Entry #{$record->id}")
            ->infolist([
                Section::make()
                    ->columns(2)
                    ->schema([
                        NepaliDateEntry::make('entry_date')->label('Date (BS)'),
                        TextEntry::make('postedBy.name')->label('Posted by')->placeholder('—'),
                        TextEntry::make('description')->columnSpanFull(),
                    ]),
                RepeatableEntry::make('lines')
                    ->hiddenLabel()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('account.name')->label('Account'),
                        TextEntry::make('debit')->money('NPR'),
                        TextEntry::make('credit')->money('NPR'),
                    ]),
            ]);
    }

    protected static function reverseAction(): Action
    {
        return Action::make('reverse')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This posts a new entry with every line reversed. The original entry is kept for the audit trail, not edited or removed.')
            ->visible(fn (JournalEntry $record): bool => (! $record->isReversed())
                && (auth()->user()?->can('Create:JournalEntry') ?? false))
            ->action(function (JournalEntry $record): void {
                try {
                    app(JournalEntryService::class)->reverse($record, auth()->user());

                    Notification::make()->title('Reversal posted.')->success()->send();
                } catch (JournalEntryAlreadyReversedException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJournalEntries::route('/'),
        ];
    }
}
