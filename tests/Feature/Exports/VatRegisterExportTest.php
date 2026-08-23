<?php

use App\Exports\VatRegisterExport;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\VatRate;
use App\Services\AccountingReportService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Company::create([
        'name' => 'Test Co',
        'accounts_receivable_account_id' => Account::factory()->create(['code' => '1200'])->id,
        'sales_revenue_account_id' => Account::factory()->revenue()->create(['code' => '4000'])->id,
        'vat_payable_account_id' => Account::factory()->liability()->create(['code' => '2100'])->id,
    ]);
    VatRate::create(['rate_percent' => 13, 'effective_from' => '2020-01-01']);

    $customer = Customer::factory()->create(['name' => 'Acme Traders', 'pan_number' => '123456789']);
    $user = User::factory()->create();

    app(InvoiceService::class)->create($customer, Carbon::parse('2026-07-01'), [
        ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
        ['description' => 'Exempt', 'quantity' => 1, 'unit_price' => 500, 'is_vatable' => false],
    ], $user);

    $this->report = app(AccountingReportService::class)->vatRegister();
});

test('headings match the on-screen register columns', function () {
    $export = new VatRegisterExport($this->report);

    expect($export->headings())->toBe(['Date (BS)', 'Invoice #', 'Customer', 'PAN', 'Taxable', 'Exempt', 'VAT', 'Total', 'Status']);
});

test('map produces a row with the correct customer, taxable/exempt split and status', function () {
    $export = new VatRegisterExport($this->report);
    $line = $this->report->lines->sole();

    $row = $export->map($line);

    expect($row[2])->toBe('Acme Traders')
        ->and($row[3])->toBe('123456789')
        ->and($row[4])->toBe(1000.0)
        ->and($row[5])->toBe(500.0)
        ->and($row[6])->toBe(130.0)
        ->and($row[7])->toBe(1630.0)
        ->and($row[8])->toBe('Issued');
});
