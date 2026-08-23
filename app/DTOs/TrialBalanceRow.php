<?php

namespace App\DTOs;

use App\Models\Account;

/**
 * One row of a trial balance report, as produced by
 * AccountingReportService::trialBalance().
 */
final readonly class TrialBalanceRow
{
    public function __construct(
        public Account $account,
        public float $debitTotal,
        public float $creditTotal,
    ) {}

    /** Net of the two totals — positive is a net debit balance, negative a net credit balance. */
    public function balance(): float
    {
        return round($this->debitTotal - $this->creditTotal, 2);
    }
}
