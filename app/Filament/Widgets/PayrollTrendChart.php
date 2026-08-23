<?php

namespace App\Filament\Widgets;

use App\Models\PayrollRun;
use App\Models\Payslip;
use Filament\Widgets\ChartWidget;

/**
 * Net pay disbursed per finalized payroll run, most recent 6 (DESIGN.md
 * D2/D3/D4 — see AttendanceTrendChart's docblock for the same reasoning on
 * skill invocation and colour). A bar chart: discrete periods being
 * compared, not a continuous series — the idiomatic form for comparing
 * distinct events, unlike the attendance chart's continuous daily trend.
 *
 * Deliberately net pay, not the full payroll *cost* (gross + employer
 * PF/SSF, the figure PayrollRunService::postToLedger() posts to the general
 * ledger) — recomputing that formula here would duplicate business logic
 * that already lives in exactly one place. Net pay is simpler, always
 * available (doesn't depend on Company's accounting configuration being
 * set), and is what "payroll cost" casually means on a dashboard glance;
 * the accounting-accurate figure belongs on the trial balance / journal
 * entries, not this widget. Labelled "Net pay", not "Cost", so it's never
 * read as the GL figure.
 *
 * Only finalized runs — a draft's numbers aren't real yet (PayrollRunService
 * docblock: finalizing is what locks a run).
 *
 * D5: PayrollRun/Payslip carry no per-manager visibility scope —
 * PayrollRunResource itself doesn't scope by visibleTo() either (payroll is
 * company-wide, not per-report), so this widget matches that.
 *
 * D6: two bounded queries (latest 6 runs, one grouped SUM over their
 * payslips) — not N+1 per-run queries.
 */
class PayrollTrendChart extends ChartWidget
{
    protected static ?int $sort = 20;

    protected ?string $heading = 'Payroll — net pay, last 6 runs';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:PayrollRun') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $runs = PayrollRun::query()
            ->where('status', PayrollRun::STATUS_FINALIZED)
            ->latest('period_start')
            ->limit(6)
            ->get()
            ->sortBy('period_start')
            ->values();

        $netPayByRun = Payslip::query()
            ->whereIn('payroll_run_id', $runs->pluck('id'))
            ->selectRaw('payroll_run_id, SUM(net_pay) as total')
            ->groupBy('payroll_run_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->payroll_run_id => (float) $row->total]);

        return [
            'labels' => $runs->map(fn (PayrollRun $run): string => $run->period_start->format('M Y'))->all(),
            'datasets' => [
                [
                    'label' => 'Net pay (NPR)',
                    'data' => $runs->map(fn (PayrollRun $run): float => round($netPayByRun->get($run->id, 0.0), 2))->all(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'NPR'],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false], // one series — the heading already names it
            ],
        ];
    }
}
