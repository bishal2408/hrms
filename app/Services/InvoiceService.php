<?php

namespace App\Services;

use App\Exceptions\EmptyInvoiceException;
use App\Exceptions\InvoiceAlreadyCancelledException;
use App\Exceptions\MissingAccountingConfigurationException;
use App\Exceptions\MissingVatRateException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only place an Invoice is written. An issued invoice is immutable
 * (CLAUDE.md: cancel-don't-delete on posted financial records) — no edit
 * action exists anywhere; `cancel()` reverses the GL posting and marks the
 * row cancelled with an audit trail, the original row is never touched.
 * Numbering is gap-free per Nepali fiscal year: the next sequence is read
 * under `lockForUpdate()` inside the same transaction as the insert, the
 * same row-locking shape AttendanceService already uses for its own
 * one-at-a-time invariant, so a concurrent create can't allocate the same
 * number twice or leave a gap on failure (the whole transaction rolls back
 * together).
 */
class InvoiceService
{
    public function __construct(private readonly JournalEntryService $journalEntryService) {}

    /**
     * @param  array<int, array{description: string, quantity: float, unit_price: float, is_vatable?: bool}>  $lines
     *
     * @throws EmptyInvoiceException
     * @throws MissingVatRateException
     * @throws MissingAccountingConfigurationException
     */
    public function create(Customer $customer, Carbon $issueDate, array $lines, ?User $createdBy, ?string $notes = null): Invoice
    {
        if ($lines === []) {
            throw new EmptyInvoiceException('An invoice needs at least one line.');
        }

        return DB::transaction(function () use ($customer, $issueDate, $lines, $createdBy, $notes): Invoice {
            $fiscalYear = (int) NepaliCalendar::fiscalYearFor($issueDate)['bs_start_year'];

            $lastSequence = (int) Invoice::query()->where('fiscal_year', $fiscalYear)->lockForUpdate()->max('sequence');
            $sequence = $lastSequence + 1;

            [$lineData, $subtotal, $vatableSubtotal] = $this->prepareLines($lines);
            $vatAmount = $vatableSubtotal > 0 ? $this->calculateVat($vatableSubtotal, $issueDate) : 0.0;
            $total = round($subtotal + $vatAmount, 2);

            $invoice = new Invoice([
                'customer_id' => $customer->id,
                'fiscal_year' => $fiscalYear,
                'sequence' => $sequence,
                'invoice_number' => sprintf('INV-%d-%04d', $fiscalYear, $sequence),
                'issue_date' => $issueDate->toDateString(),
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
                'notes' => $notes,
            ]);
            $invoice->created_by = $createdBy?->id;
            $invoice->save();

            foreach ($lineData as $data) {
                $invoice->lineItems()->create($data);
            }

            $this->postToLedger($invoice, $createdBy);

            return $invoice;
        });
    }

    /**
     * @throws InvoiceAlreadyCancelledException
     */
    public function cancel(Invoice $invoice, User $cancelledBy, string $reason): Invoice
    {
        if ($invoice->isCancelled()) {
            throw new InvoiceAlreadyCancelledException("Invoice {$invoice->invoice_number} is already cancelled.");
        }

        return DB::transaction(function () use ($invoice, $cancelledBy, $reason): Invoice {
            $journalEntry = $invoice->journalEntry();

            if ($journalEntry !== null) {
                $this->journalEntryService->reverse($journalEntry, $cancelledBy);
            }

            $invoice->status = Invoice::STATUS_CANCELLED;
            $invoice->cancelled_by = $cancelledBy->id;
            $invoice->cancelled_at = now();
            $invoice->cancellation_reason = $reason;
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * @param  array<int, array{description: string, quantity: float, unit_price: float, is_vatable?: bool}>  $lines
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float}
     */
    private function prepareLines(array $lines): array
    {
        $lineData = [];
        $subtotal = 0.0;
        $vatableSubtotal = 0.0;

        foreach ($lines as $line) {
            $amount = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $isVatable = $line['is_vatable'] ?? true;

            $subtotal += $amount;

            if ($isVatable) {
                $vatableSubtotal += $amount;
            }

            $lineData[] = [
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'is_vatable' => $isVatable,
                'amount' => $amount,
            ];
        }

        return [$lineData, round($subtotal, 2), round($vatableSubtotal, 2)];
    }

    /**
     * @throws MissingVatRateException
     */
    private function calculateVat(float $vatableSubtotal, Carbon $issueDate): float
    {
        $vatRate = VatRate::currentFor($issueDate);

        if ($vatRate === null) {
            throw new MissingVatRateException("No VAT rate is configured for {$issueDate->toDateString()}.");
        }

        return round($vatableSubtotal * ((float) $vatRate->rate_percent / 100), 2);
    }

    /**
     * @throws MissingAccountingConfigurationException
     */
    private function postToLedger(Invoice $invoice, ?User $postedBy): void
    {
        $company = Company::current();
        $needsVatAccount = (float) $invoice->vat_amount > 0;

        if ($company->accounts_receivable_account_id === null
            || $company->sales_revenue_account_id === null
            || ($needsVatAccount && $company->vat_payable_account_id === null)) {
            throw new MissingAccountingConfigurationException(
                'Configure the Accounts Receivable, Sales Revenue and VAT Payable accounts in Company Settings before issuing invoices.'
            );
        }

        $lines = [
            ['account_id' => $company->accounts_receivable_account_id, 'debit' => (float) $invoice->total, 'credit' => 0, 'description' => "Invoice {$invoice->invoice_number}"],
            ['account_id' => $company->sales_revenue_account_id, 'debit' => 0, 'credit' => (float) $invoice->subtotal, 'description' => "Invoice {$invoice->invoice_number}"],
        ];

        if ($needsVatAccount) {
            $lines[] = ['account_id' => $company->vat_payable_account_id, 'debit' => 0, 'credit' => (float) $invoice->vat_amount, 'description' => "VAT on invoice {$invoice->invoice_number}"];
        }

        $this->journalEntryService->post(
            entryDate: $invoice->issue_date,
            description: "Sales invoice {$invoice->invoice_number}",
            lines: $lines,
            postedBy: $postedBy,
            referenceType: Invoice::REFERENCE_TYPE,
            referenceId: $invoice->id,
        );
    }
}
