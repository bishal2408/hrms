<?php

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Exceptions\UnbalancedJournalEntryException;
use App\Filament\Forms\Components\NepaliDatePicker;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Models\Account;
use App\Services\JournalEntryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ManageJournalEntries extends ManageRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->postAction(),
        ];
    }

    protected function postAction(): Action
    {
        return Action::make('post')
            ->label('New journal entry')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->visible(fn (): bool => auth()->user()?->can('Create:JournalEntry') ?? false)
            ->schema([
                NepaliDatePicker::make('entry_date')
                    ->label('Date (BS)')
                    ->required()
                    ->default(now()->toDateString()),
                Textarea::make('description')
                    ->required()
                    ->maxLength(1000),
                Repeater::make('lines')
                    ->label('Lines')
                    ->schema([
                        Select::make('account_id')
                            ->label('Account')
                            ->options(fn () => Account::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(
                                fn (Account $account): array => [$account->id => "{$account->code} — {$account->name}"],
                            ))
                            ->searchable()
                            ->required(),
                        TextInput::make('debit')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('credit')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('description')
                            ->maxLength(255),
                    ])
                    ->columns(4)
                    ->minItems(2)
                    ->defaultItems(2)
                    ->addActionLabel('Add line')
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    app(JournalEntryService::class)->post(
                        entryDate: Carbon::parse($data['entry_date']),
                        description: $data['description'],
                        lines: $data['lines'],
                        postedBy: auth()->user(),
                    );

                    Notification::make()->title('Journal entry posted.')->success()->send();
                } catch (UnbalancedJournalEntryException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
