<?php

namespace App\Services;

use App\DTOs\TrialBalanceRow;
use App\DTOs\VatRegisterLine;
use App\DTOs\VatRegisterReport;
use App\Models\Account;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only accounting reports — no writes here, see JournalEntryService
 * and InvoiceService for those. Most of these read the ledger directly;
 * vatRegister() reads Invoice/InvoiceLineItem instead, since VAT is a sales-
 * document concept, not a GL-account concept — the ledger's VAT Payable
 * account carries the same figure, but "VAT collected per invoice, this
 * period" is naturally an invoice-level report.
 */
class AccountingReportService
{
    /**
     * Every active account's total debits/credits, optionally as of a given
     * date rather than all-time. In a correctly-functioning ledger the
     * overall debit and credit totals always tie out — every entry that
     * ever reached the database was balance-checked by
     * JournalEntryService::post() before it was written.
     *
     * @return Collection<int, TrialBalanceRow>
     */
    public function trialBalance(?Carbon $asOf = null): Collection
    {
        return Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account): TrialBalanceRow => new TrialBalanceRow(
                account: $account,
                debitTotal: $this->lineTotal($account, 'debit', $asOf),
                creditTotal: $this->lineTotal($account, 'credit', $asOf),
            ));
    }

    private function lineTotal(Account $account, string $column, ?Carbon $asOf): float
    {
        return (float) $account->journalLines()
            // whereDate(), not whereBetween/plain <=, on a date-cast column
            // (CLAUDE.md's Coding conventions) — SQLite's test DB stores
            // entry_date with a time suffix that string-compares wrong
            // against a bare date otherwise.
            ->when($asOf, fn (Builder $query) => $query->whereHas(
                'journalEntry',
                fn (Builder $entryQuery) => $entryQuery->whereDate('entry_date', '<=', $asOf),
            ))
            ->sum($column);
    }

    /**
     * Every invoice issued in a period (both statuses — cancelled invoices
     * still appear, for audit continuity, but never count toward the
     * totals: their GL effect was already reversed). Output VAT only —
     * input/purchase VAT was explicitly deferred (see docs/ROADMAP.md's
     * Phase 4 decision note).
     */
    public function vatRegister(?Carbon $from = null, ?Carbon $until = null): VatRegisterReport
    {
        $lines = Invoice::query()
            ->with(['customer', 'lineItems'])
            // whereDate(), not a plain >=/<=, on a date-cast column — same
            // reasoning as lineTotal() above.
            ->when($from, fn (Builder $query) => $query->whereDate('issue_date', '>=', $from))
            ->when($until, fn (Builder $query) => $query->whereDate('issue_date', '<=', $until))
            ->orderBy('issue_date')
            ->orderBy('sequence')
            ->get()
            ->map(fn (Invoice $invoice): VatRegisterLine => new VatRegisterLine(
                invoice: $invoice,
                taxableAmount: round((float) $invoice->lineItems->where('is_vatable', true)->sum('amount'), 2),
                exemptAmount: round((float) $invoice->lineItems->where('is_vatable', false)->sum('amount'), 2),
            ));

        $issued = $lines->filter(fn (VatRegisterLine $line): bool => ! $line->invoice->isCancelled());

        return new VatRegisterReport(
            lines: $lines,
            totalTaxable: round((float) $issued->sum('taxableAmount'), 2),
            totalExempt: round((float) $issued->sum('exemptAmount'), 2),
            totalVat: round((float) $issued->sum(fn (VatRegisterLine $line): float => (float) $line->invoice->vat_amount), 2),
        );
    }
}
