<?php

use App\Exceptions\EmptyInvoiceException;
use App\Exceptions\InvoiceAlreadyCancelledException;
use App\Exceptions\MissingAccountingConfigurationException;
use App\Exceptions\MissingVatRateException;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\VatRate;
use App\Services\InvoiceService;
use App\Services\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InvoiceService(new JournalEntryService);
    $this->customer = Customer::factory()->create();
    $this->creator = User::factory()->create();

    $this->ar = Account::factory()->create(['code' => '1200', 'name' => 'Accounts Receivable']);
    $this->revenue = Account::factory()->revenue()->create(['code' => '4000', 'name' => 'Sales Revenue']);
    $this->vatPayable = Account::factory()->liability()->create(['code' => '2100', 'name' => 'VAT Payable']);

    Company::create([
        'name' => 'Test Co',
        'accounts_receivable_account_id' => $this->ar->id,
        'sales_revenue_account_id' => $this->revenue->id,
        'vat_payable_account_id' => $this->vatPayable->id,
    ]);
});

test('an invoice with no vatable lines posts a two-line entry: debit AR, credit revenue', function () {
    $invoice = $this->service->create(
        customer: $this->customer,
        issueDate: Carbon::parse('2026-07-01'),
        lines: [
            ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 500, 'is_vatable' => false],
        ],
        createdBy: $this->creator,
    );

    expect((float) $invoice->subtotal)->toBe(1000.0)
        ->and((float) $invoice->vat_amount)->toBe(0.0)
        ->and((float) $invoice->total)->toBe(1000.0);

    $entry = $invoice->journalEntry();
    expect($entry)->not->toBeNull()
        ->and($entry->lines)->toHaveCount(2);

    $arLine = $entry->lines->firstWhere('account_id', $this->ar->id);
    $revenueLine = $entry->lines->firstWhere('account_id', $this->revenue->id);
    expect((float) $arLine->debit)->toBe(1000.0)
        ->and((float) $revenueLine->credit)->toBe(1000.0);
});

test('a vatable line resolves the current VAT rate and posts a third VAT Payable line', function () {
    VatRate::create(['rate_percent' => 13, 'effective_from' => '2020-01-01']);

    $invoice = $this->service->create(
        customer: $this->customer,
        issueDate: Carbon::parse('2026-07-01'),
        lines: [
            ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
        ],
        createdBy: $this->creator,
    );

    expect((float) $invoice->subtotal)->toBe(1000.0)
        ->and((float) $invoice->vat_amount)->toBe(130.0)
        ->and((float) $invoice->total)->toBe(1130.0);

    $entry = $invoice->journalEntry();
    expect($entry->lines)->toHaveCount(3);

    $vatLine = $entry->lines->firstWhere('account_id', $this->vatPayable->id);
    expect((float) $vatLine->credit)->toBe(130.0);
});

test('mixed vatable and exempt lines only charge VAT on the vatable portion', function () {
    VatRate::create(['rate_percent' => 13, 'effective_from' => '2020-01-01']);

    $invoice = $this->service->create(
        customer: $this->customer,
        issueDate: Carbon::parse('2026-07-01'),
        lines: [
            ['description' => 'Taxable goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
            ['description' => 'Exempt export', 'quantity' => 1, 'unit_price' => 500, 'is_vatable' => false],
        ],
        createdBy: $this->creator,
    );

    expect((float) $invoice->subtotal)->toBe(1500.0)
        ->and((float) $invoice->vat_amount)->toBe(130.0) // 13% of 1000 only
        ->and((float) $invoice->total)->toBe(1630.0);
});

test('invoice numbers are sequential and gap-free within a fiscal year', function () {
    $first = $this->service->create($this->customer, Carbon::parse('2026-07-01'), [
        ['description' => 'A', 'quantity' => 1, 'unit_price' => 100, 'is_vatable' => false],
    ], $this->creator);
    $second = $this->service->create($this->customer, Carbon::parse('2026-07-05'), [
        ['description' => 'B', 'quantity' => 1, 'unit_price' => 100, 'is_vatable' => false],
    ], $this->creator);

    expect($first->sequence)->toBe(1)
        ->and($second->sequence)->toBe(2)
        ->and($first->fiscal_year)->toBe($second->fiscal_year)
        ->and($second->invoice_number)->toBe(sprintf('INV-%d-%04d', $second->fiscal_year, 2));
});

test('an empty invoice is rejected', function () {
    $this->service->create($this->customer, Carbon::parse('2026-07-01'), [], $this->creator);
})->throws(EmptyInvoiceException::class);

test('a vatable line with no VAT rate configured is rejected', function () {
    $this->service->create($this->customer, Carbon::parse('2026-07-01'), [
        ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
    ], $this->creator);
})->throws(MissingVatRateException::class);

test('a rejected vatable line with no VAT rate leaves no orphaned invoice row', function () {
    try {
        $this->service->create($this->customer, Carbon::parse('2026-07-01'), [
            ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
        ], $this->creator);
    } catch (MissingVatRateException) {
        // expected
    }

    expect(Invoice::count())->toBe(0);
});

test('missing accounting configuration is rejected and nothing is written', function () {
    Company::current()->update(['accounts_receivable_account_id' => null]);

    $this->service->create($this->customer, Carbon::parse('2026-07-01'), [
        ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false],
    ], $this->creator);
})->throws(MissingAccountingConfigurationException::class);

test('missing accounting configuration leaves no orphaned invoice row', function () {
    Company::current()->update(['accounts_receivable_account_id' => null]);

    try {
        $this->service->create($this->customer, Carbon::parse('2026-07-01'), [
            ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false],
        ], $this->creator);
    } catch (MissingAccountingConfigurationException) {
        // expected
    }

    expect(Invoice::count())->toBe(0);
});

test('cancelling an invoice reverses its GL posting and records the audit trail', function () {
    $invoice = $this->service->create($this->customer, Carbon::parse('2026-07-01'), [
        ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false],
    ], $this->creator);
    $canceller = User::factory()->create();

    $cancelled = $this->service->cancel($invoice, $canceller, 'Customer changed their mind');

    expect($cancelled->status)->toBe(Invoice::STATUS_CANCELLED)
        ->and($cancelled->cancelled_by)->toBe($canceller->id)
        ->and($cancelled->cancellation_reason)->toBe('Customer changed their mind')
        ->and($cancelled->cancelled_at)->not->toBeNull();

    $originalEntry = $invoice->journalEntry();
    expect($originalEntry->isReversed())->toBeTrue()
        ->and(JournalEntry::count())->toBe(2); // original + reversal
});

test('an invoice cannot be cancelled twice', function () {
    $invoice = $this->service->create($this->customer, Carbon::parse('2026-07-01'), [
        ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false],
    ], $this->creator);
    $canceller = User::factory()->create();

    $this->service->cancel($invoice, $canceller, 'First cancellation');
    $this->service->cancel($invoice, $canceller, 'Second attempt');
})->throws(InvoiceAlreadyCancelledException::class);
