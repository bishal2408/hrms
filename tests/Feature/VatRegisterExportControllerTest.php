<?php

use App\Exports\VatRegisterExport;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\VatRate;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web');
    Role::findOrCreate('employee', 'web');

    Company::create([
        'name' => 'Test Co',
        'accounts_receivable_account_id' => Account::factory()->create(['code' => '1200'])->id,
        'sales_revenue_account_id' => Account::factory()->revenue()->create(['code' => '4000'])->id,
        'vat_payable_account_id' => Account::factory()->liability()->create(['code' => '2100'])->id,
    ]);
    VatRate::create(['rate_percent' => 13, 'effective_from' => '2020-01-01']);
});

test('payroll_accountant can download the VAT register as an Excel file', function () {
    Excel::fake();

    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    app(InvoiceService::class)->create(Customer::factory()->create(), Carbon::parse('2026-07-01'), [
        ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
    ], $accountant);

    $this->actingAs($accountant)
        ->get(route('vat-register.export', ['from' => '2026-07-01', 'until' => '2026-07-31']))
        ->assertOk();

    Excel::assertDownloaded('vat-register.xlsx', fn (VatRegisterExport $export): bool => $export->collection()->count() === 1);
});

test('a user without accounting access cannot download the VAT register', function () {
    $employee = User::factory()->create()->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('vat-register.export'))
        ->assertForbidden();
});

test('an unauthenticated request is forbidden, not shown the file', function () {
    $this->get(route('vat-register.export'))->assertForbidden();
});

test('the real generated file contains the correct data and a totals row, cancelled invoices excluded from the total', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $customer = Customer::factory()->create(['name' => 'Acme Traders']);
    $invoiceService = app(InvoiceService::class);

    $invoiceService->create($customer, Carbon::parse('2026-07-01'), [
        ['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true],
    ], $accountant);

    $cancelled = $invoiceService->create($customer, Carbon::parse('2026-07-02'), [
        ['description' => 'Cancelled sale', 'quantity' => 1, 'unit_price' => 999, 'is_vatable' => true],
    ], $accountant);
    $invoiceService->cancel($cancelled, $accountant, 'Test');

    $response = $this->actingAs($accountant)
        ->get(route('vat-register.export', ['from' => '2026-07-01', 'until' => '2026-07-31']))
        ->assertOk();

    $path = $response->getFile()->getPathname();
    $sheet = IOFactory::load($path)->getActiveSheet();

    // Row 1 = headings, row 2 = the issued invoice, row 3 = the cancelled
    // one, row 4 = totals (2 data rows + header + totals).
    expect($sheet->getCell('A1')->getValue())->toBe('Date (BS)')
        ->and($sheet->getCell('C2')->getValue())->toBe('Acme Traders')
        ->and((float) $sheet->getCell('E2')->getValue())->toBe(1000.0)
        ->and($sheet->getCell('I3')->getValue())->toBe('Cancelled')
        ->and((float) $sheet->getCell('E4')->getValue())->toBe(1000.0) // totals row excludes the cancelled 999
        ->and((float) $sheet->getCell('G4')->getValue())->toBe(130.0);
});
