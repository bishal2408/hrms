<?php

use App\Filament\Pages\VatRegisterPage;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\VatRate;
use App\Services\InvoiceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web');
    Role::findOrCreate('manager', 'web');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('payroll_accountant can access the VAT register page', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    expect(VatRegisterPage::canAccess())->toBeTrue();
});

test('a manager cannot access the VAT register page', function () {
    $manager = User::factory()->create()->assignRole('manager');
    $this->actingAs($manager);

    expect(VatRegisterPage::canAccess())->toBeFalse();
});

test('the page shows correct totals for issued invoices', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    $ar = Account::factory()->create(['code' => '1200']);
    $revenue = Account::factory()->revenue()->create(['code' => '4000']);
    $vatPayable = Account::factory()->liability()->create(['code' => '2100']);
    Company::create([
        'name' => 'Test Co',
        'accounts_receivable_account_id' => $ar->id,
        'sales_revenue_account_id' => $revenue->id,
        'vat_payable_account_id' => $vatPayable->id,
    ]);
    VatRate::create(['rate_percent' => 13, 'effective_from' => '2020-01-01']);

    app(InvoiceService::class)->create(
        Customer::factory()->create(),
        Carbon::parse('2026-07-01'),
        [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true]],
        $accountant,
    );

    Livewire::test(VatRegisterPage::class)
        ->assertSet('totalTaxable', 1000.0)
        ->assertSet('totalVat', 130.0);
});
