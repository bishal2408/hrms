<?php

namespace App\Filament\Pages;

use App\DTOs\TrialBalanceRow;
use App\Filament\Forms\Components\NepaliDatePicker;
use App\Services\AccountingReportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Read-only report — no save action, the filter form just narrows the ledger read. */
class TrialBalance extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.trial-balance';

    protected static ?string $slug = 'accounting/trial-balance';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Trial Balance';

    protected static ?int $navigationSort = 30;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Accounting is payroll_accountant's domain throughout this app (PF/SSF rates, tax slabs, salary structures) — same reasoning here. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'payroll_accountant']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                NepaliDatePicker::make('as_of')
                    ->label('As of (BS)')
                    ->live()
                    ->helperText('Leave empty to include every posted entry.'),
            ])
            ->statePath('data');
    }

    /** @return Collection<int, TrialBalanceRow> */
    public function getRowsProperty(): Collection
    {
        $asOfInput = $this->form->getState()['as_of'] ?? null;

        return app(AccountingReportService::class)->trialBalance(
            filled($asOfInput) ? Carbon::parse($asOfInput) : null,
        );
    }

    public function getTotalDebitProperty(): float
    {
        return round($this->rows->sum('debitTotal'), 2);
    }

    public function getTotalCreditProperty(): float
    {
        return round($this->rows->sum('creditTotal'), 2);
    }
}
