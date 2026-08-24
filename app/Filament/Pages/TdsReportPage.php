<?php

namespace App\Filament\Pages;

use App\DTOs\TdsReport;
use App\Filament\Forms\Components\NepaliDatePicker;
use App\Services\PayrollComplianceReportService;
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
 * Mirrors VatRegisterPage/PfSsfRemittancePage exactly. Named TdsReportPage
 * (not TdsReport) to avoid colliding with a future TdsReport model, same
 * VatRegisterPage-vs-VatRate naming reasoning.
 */
class TdsReportPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.tds-report';

    protected static ?string $title = 'TDS Report';

    protected static ?string $slug = 'payroll/tds-report';

    protected static string|UnitEnum|null $navigationGroup = 'Payroll';

    // Not OutlinedReceiptPercent: PayrollRateResource and VatRateResource
    // already use it — DESIGN.md's "Never ship the generator's default icon
    // on more than one resource" rule extends to any duplicate nav icon,
    // not just the generator's literal default.
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPercentBadge;

    protected static ?string $navigationLabel = 'TDS Report';

    protected static ?int $navigationSort = 31;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Payroll compliance reports are payroll_accountant's domain throughout this app — same reasoning as VatRegisterPage. */
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
                    ->helperText('Leave both empty to include every finalized run.'),
                NepaliDatePicker::make('until')
                    ->label('Until (BS)')
                    ->live(),
            ])
            ->statePath('data');
    }

    public function getReportProperty(): TdsReport
    {
        [$from, $until] = $this->resolveDateRange();

        return app(PayrollComplianceReportService::class)->tds($from, $until);
    }

    /** The currently-selected range, as AD query params — TdsReportExportController reads these directly, no BS parsing happens there. */
    public function getExportUrlProperty(): string
    {
        [$from, $until] = $this->resolveDateRange();

        return route('tds-report.export', array_filter([
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
