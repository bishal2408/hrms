<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\VatRate;
use App\Services\AccountingReportService;
use App\Services\InvoiceService;
use App\Services\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reportService = new AccountingReportService;
    $this->journalService = new JournalEntryService;
    $this->cash = Account::factory()->create(['code' => '1000', 'name' => 'Cash']);
    $this->revenue = Account::factory()->revenue()->create(['code' => '4000', 'name' => 'Sales Revenue']);
    $this->poster = User::factory()->create();
});

test('trial balance sums debits and credits per account', function () {
    $this->journalService->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Cash sale',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ],
        postedBy: $this->poster,
    );
    $this->journalService->post(
        entryDate: Carbon::parse('2026-07-05'),
        description: 'Another cash sale',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ],
        postedBy: $this->poster,
    );

    $rows = $this->reportService->trialBalance();
    $cashRow = $rows->first(fn ($row) => $row->account->is($this->cash));
    $revenueRow = $rows->first(fn ($row) => $row->account->is($this->revenue));

    expect($cashRow->debitTotal)->toBe(1500.0)
        ->and($cashRow->creditTotal)->toBe(0.0)
        ->and($cashRow->balance())->toBe(1500.0)
        ->and($revenueRow->creditTotal)->toBe(1500.0)
        ->and($revenueRow->balance())->toBe(-1500.0);
});

test('the total debits and total credits across the whole trial balance always tie out', function () {
    $this->journalService->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Cash sale',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 750, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 750],
        ],
        postedBy: $this->poster,
    );

    $rows = $this->reportService->trialBalance();

    expect($rows->sum('debitTotal'))->toBe($rows->sum('creditTotal'));
});

test('an "as of" date excludes entries posted after it, including on the boundary date itself', function () {
    $this->journalService->post(
        entryDate: Carbon::parse('2026-07-10'),
        description: 'On the boundary',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 100],
        ],
        postedBy: $this->poster,
    );
    $this->journalService->post(
        entryDate: Carbon::parse('2026-07-11'),
        description: 'After the boundary',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 200, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 200],
        ],
        postedBy: $this->poster,
    );

    $rows = $this->reportService->trialBalance(Carbon::parse('2026-07-10'));
    $cashRow = $rows->first(fn ($row) => $row->account->is($this->cash));

    // The boundary-date entry (07-10) must be included, the later one
    // (07-11) must not — this is exactly the whereDate()-vs-whereBetween()
    // boundary case documented in CLAUDE.md's Coding conventions.
    expect($cashRow->debitTotal)->toBe(100.0);
});

test('inactive accounts are excluded from the trial balance', function () {
    Account::factory()->create(['code' => '9999', 'name' => 'Old Account', 'is_active' => false]);

    $rows = $this->reportService->trialBalance();

    expect($rows->pluck('account.code'))->not->toContain('9999');
});

beforeEach(function () {
    $this->ar = Account::factory()->create(['code' => '1200', 'name' => 'Accounts Receivable']);
    $this->vatPayable = Account::factory()->liability()->create(['code' => '2100', 'name' => 'VAT Payable']);
    Company::create([
        'name' => 'Test Co',
        'accounts_receivable_account_id' => $this->ar->id,
        'sales_revenue_account_id' => $this->revenue->id,
        'vat_payable_account_id' => $this->vatPayable->id,
    ]);
    VatRate::create(['rate_percent' => 13, 'effective_from' => '2020-01-01']);
    $this->invoiceService = new InvoiceService($this->journalService);
    $this->customer = Customer::factory()->create();
});

test('vatRegister splits each invoice into taxable and exempt totals and sums VAT across the period', function () {
    $this->invoiceService->create($this->customer, Carbon::parse('2026-07-01'), [
        ['description' => 'Taxable', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
        ['description' => 'Exempt', 'quantity' => 1, 'unit_price' => 500, 'is_vatable' => false],
    ], $this->poster);

    $report = $this->reportService->vatRegister();

    expect($report->lines)->toHaveCount(1)
        ->and($report->lines->first()->taxableAmount)->toBe(1000.0)
        ->and($report->lines->first()->exemptAmount)->toBe(500.0)
        ->and($report->totalTaxable)->toBe(1000.0)
        ->and($report->totalExempt)->toBe(500.0)
        ->and($report->totalVat)->toBe(130.0)
        ->and($report->totalSales())->toBe(1500.0);
});

test('vatRegister still lists a cancelled invoice but excludes it from the totals', function () {
    $invoice = $this->invoiceService->create($this->customer, Carbon::parse('2026-07-01'), [
        ['description' => 'Taxable', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
    ], $this->poster);
    $this->invoiceService->cancel($invoice, $this->poster, 'Test cancellation');

    $report = $this->reportService->vatRegister();

    expect($report->lines)->toHaveCount(1)
        ->and($report->totalTaxable)->toBe(0.0)
        ->and($report->totalVat)->toBe(0.0);
});

test('vatRegister excludes invoices outside the date range, including on the boundary date itself', function () {
    $this->invoiceService->create($this->customer, Carbon::parse('2026-07-10'), [
        ['description' => 'On the boundary', 'quantity' => 1, 'unit_price' => 100, 'is_vatable' => false],
    ], $this->poster);
    $this->invoiceService->create($this->customer, Carbon::parse('2026-07-11'), [
        ['description' => 'After the boundary', 'quantity' => 1, 'unit_price' => 200, 'is_vatable' => false],
    ], $this->poster);

    $report = $this->reportService->vatRegister(until: Carbon::parse('2026-07-10'));

    // The boundary-date invoice (07-10) must be included, the later one
    // (07-11) must not — the same whereDate() boundary case as
    // trialBalance's own "as of" test above.
    expect($report->lines)->toHaveCount(1)
        ->and($report->totalExempt)->toBe(100.0);
});

test('vatRegister is empty with zero totals when there are no invoices at all', function () {
    $report = $this->reportService->vatRegister();

    expect($report->lines)->toBeEmpty()
        ->and($report->totalTaxable)->toBe(0.0)
        ->and($report->totalExempt)->toBe(0.0)
        ->and($report->totalVat)->toBe(0.0);
});
