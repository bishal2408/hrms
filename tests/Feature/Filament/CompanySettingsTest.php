<?php

use App\Filament\Pages\CompanySettings;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'hr_admin', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('an hr_admin can update the company profile', function () {
    $admin = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($admin);

    Livewire::test(CompanySettings::class)
        ->fillForm([
            'name' => 'Acme Pvt. Ltd.',
            'pan_number' => '123456789',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Company::current()->name)->toBe('Acme Pvt. Ltd.')
        ->and(Company::current()->pan_number)->toBe('123456789');
});

test('an hr_admin can set the accounting default accounts', function () {
    $admin = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($admin);

    $ar = Account::factory()->create(['code' => '1200', 'name' => 'Accounts Receivable']);
    $revenue = Account::factory()->revenue()->create(['code' => '4000', 'name' => 'Sales Revenue']);
    $vat = Account::factory()->liability()->create(['code' => '2100', 'name' => 'VAT Payable']);

    Livewire::test(CompanySettings::class)
        ->fillForm([
            'name' => 'Acme Pvt. Ltd.',
            'accounts_receivable_account_id' => $ar->id,
            'sales_revenue_account_id' => $revenue->id,
            'vat_payable_account_id' => $vat->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Company::current()->accounts_receivable_account_id)->toBe($ar->id)
        ->and(Company::current()->sales_revenue_account_id)->toBe($revenue->id)
        ->and(Company::current()->vat_payable_account_id)->toBe($vat->id);
});

test('an hr_admin can set the payroll accounting default accounts', function () {
    $admin = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($admin);

    $expense = Account::factory()->expense()->create(['code' => '5100', 'name' => 'Salary Expense']);
    $payable = Account::factory()->liability()->create(['code' => '2200', 'name' => 'Salary Payable']);
    $statutory = Account::factory()->liability()->create(['code' => '2300', 'name' => 'Statutory Payable']);

    Livewire::test(CompanySettings::class)
        ->fillForm([
            'name' => 'Acme Pvt. Ltd.',
            'salary_expense_account_id' => $expense->id,
            'salary_payable_account_id' => $payable->id,
            'statutory_payable_account_id' => $statutory->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Company::current()->salary_expense_account_id)->toBe($expense->id)
        ->and(Company::current()->salary_payable_account_id)->toBe($payable->id)
        ->and(Company::current()->statutory_payable_account_id)->toBe($statutory->id);
});

test('an hr_admin can switch the payroll salary calculation mode', function () {
    $admin = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($admin);

    Livewire::test(CompanySettings::class)
        ->fillForm([
            'name' => 'Acme Pvt. Ltd.',
            'payroll_salary_calculation_mode' => Company::PAYROLL_MODE_FULL_SALARY,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Company::current()->payroll_salary_calculation_mode)->toBe(Company::PAYROLL_MODE_FULL_SALARY);
});

test('a plain employee cannot access company settings', function () {
    $employee = User::factory()->create()->assignRole('employee');
    $this->actingAs($employee);

    expect(CompanySettings::canAccess())->toBeFalse();

    Livewire::test(CompanySettings::class)->assertForbidden();
});
