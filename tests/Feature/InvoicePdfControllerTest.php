<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web')->givePermissionTo(
        Permission::findOrCreate('View:Invoice', 'web'),
    );
    Role::findOrCreate('employee', 'web');

    Company::create([
        'name' => 'Test Co',
        'accounts_receivable_account_id' => Account::factory()->create(['code' => '1200'])->id,
        'sales_revenue_account_id' => Account::factory()->revenue()->create(['code' => '4000'])->id,
    ]);
});

test('staff with View:Invoice can download an invoice PDF', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $invoice = app(InvoiceService::class)->create(
        Customer::factory()->create(),
        Carbon::parse('2026-07-01'),
        [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false]],
        $accountant,
    );

    $this->actingAs($accountant)
        ->get(route('invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a user without View:Invoice cannot download an invoice PDF', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $invoice = app(InvoiceService::class)->create(
        Customer::factory()->create(),
        Carbon::parse('2026-07-01'),
        [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false]],
        $accountant,
    );

    $employee = User::factory()->create()->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('invoices.pdf', $invoice))
        ->assertForbidden();
});

test('an unauthenticated request is forbidden, not shown the PDF', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $invoice = app(InvoiceService::class)->create(
        Customer::factory()->create(),
        Carbon::parse('2026-07-01'),
        [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false]],
        $accountant,
    );

    $this->get(route('invoices.pdf', $invoice))->assertForbidden();
});
