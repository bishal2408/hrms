<?php

namespace App\DTOs;

use Illuminate\Support\Carbon;

/**
 * The full breakdown of one employee's pay for one period, as produced by
 * PayrollCalculationService. Immutable and carries every line a payslip
 * needs (Phase 3b) — not just the final net figure — so the payslip renders
 * exactly what was calculated rather than re-deriving it.
 *
 * @param  array<int, array{name: string, amount: float}>  $allowanceItems
 * @param  array<int, array{name: string, amount: float}>  $deductionItems
 */
final readonly class PayrollCalculationResult
{
    public function __construct(
        public int $employeeId,
        public Carbon $periodStart,
        public Carbon $periodEnd,
        public float $basicSalary,
        public int $totalDays,
        public int $unpaidDays,
        public float $basicAfterAttendance,
        public array $allowanceItems,
        public array $deductionItems,
        public float $allowancesTotal,
        public float $deductionsTotal,
        public float $grossPay,
        public float $pfEmployee,
        public float $pfEmployer,
        public float $ssfEmployee,
        public float $ssfEmployer,
        public float $taxableIncome,
        public float $tds,
        public float $netPay,
    ) {}
}
