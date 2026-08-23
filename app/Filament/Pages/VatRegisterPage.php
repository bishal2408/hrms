<?php

namespace App\Filament\Pages;

use App\DTOs\VatRegisterReport;
use App\Filament\Forms\Components\NepaliDatePicker;
use App\Services\AccountingReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Read-only report — no save action, the filter form just narrows the read.
 * Named VatRegisterPage (not VatRegister) so it can't be confused with the
 * VatRate model/resource pair at a glance.
 */
class VatRegisterPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.vat-register';

    protected static ?string $title = 'VAT Register';

    protected static ?string $slug = 'accounting/vat-register';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'VAT Register';

    protected static ?int $navigationSort = 35;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Accounting is payroll_accountant's domain throughout this app — same reasoning as TrialBalance. */
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

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadExcel')
                ->label('Download Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => $this->exportUrl)
                ->openUrlInNewTab(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                NepaliDatePicker::make('from')
                    ->label('From (BS)')
                    ->live()
                    ->helperText('Leave both empty to include every issued invoice.'),
                NepaliDatePicker::make('until')
                    ->label('Until (BS)')
                    ->live(),
            ])
            ->statePath('data');
    }

    public function getReportProperty(): VatRegisterReport
    {
        [$from, $until] = $this->resolveDateRange();

        return app(AccountingReportService::class)->vatRegister($from, $until);
    }

    public function getTotalTaxableProperty(): float
    {
        return $this->report->totalTaxable;
    }

    public function getTotalExemptProperty(): float
    {
        return $this->report->totalExempt;
    }

    public function getTotalVatProperty(): float
    {
        return $this->report->totalVat;
    }

    /** The currently-selected range, as AD query params — VatRegisterExportController reads these directly, no BS parsing happens there. */
    public function getExportUrlProperty(): string
    {
        [$from, $until] = $this->resolveDateRange();

        return route('vat-register.export', array_filter([
            'from' => $from?->toDateString(),
            'until' => $until?->toDateString(),
        ]));
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function resolveDateRange(): array
    {
        $state = $this->form->getState();

        return [
            filled($state['from'] ?? null) ? Carbon::parse($state['from']) : null,
            filled($state['until'] ?? null) ? Carbon::parse($state['until']) : null,
        ];
    }
}
