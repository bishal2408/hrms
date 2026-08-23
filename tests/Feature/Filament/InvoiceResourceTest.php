<?php

use App\Filament\Resources\Invoices\Pages\ManageInvoices;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:Invoice', 'web'),
        Permission::findOrCreate('View:Invoice', 'web'),
        Permission::findOrCreate('Create:Invoice', 'web'),
        Permission::findOrCreate('Delete:Invoice', 'web'),
    );

    $this->accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($this->accountant);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->customer = Customer::factory()->create();

    Company::create([
        'name' => 'Test Co',
        'accounts_receivable_account_id' => Account::factory()->create(['code' => '1200'])->id,
        'sales_revenue_account_id' => Account::factory()->revenue()->create(['code' => '4000'])->id,
    ]);
});

test('issuing an invoice through the "New invoice" action creates it', function () {
    Livewire::test(ManageInvoices::class)
        ->callAction('createInvoice', data: [
            'customer_id' => $this->customer->id,
            'issue_date' => '2083-03-15',
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false],
            ],
        ]);

    expect(Invoice::count())->toBe(1)
        ->and(Invoice::first()->customer_id)->toBe($this->customer->id);
});

test('the "New invoice" action is hidden without Create:Invoice', function () {
    Role::findOrCreate('manager', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:Invoice', 'web'),
    );
    $manager = User::factory()->create()->assignRole('manager');
    $this->actingAs($manager);

    Livewire::test(ManageInvoices::class)
        ->assertActionHidden('createInvoice');
});

test('cancelling an invoice reverses it and hides the cancel action afterwards', function () {
    $invoice = app(InvoiceService::class)->create(
        $this->customer,
        Carbon::parse('2026-07-01'),
        [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => false]],
        $this->accountant,
    );

    Livewire::test(ManageInvoices::class)
        ->callAction(TestAction::make('cancel')->table($invoice), data: ['reason' => 'Mistake']);

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_CANCELLED);

    Livewire::test(ManageInvoices::class)
        ->assertActionHidden(TestAction::make('cancel')->table($invoice->fresh()));
});
