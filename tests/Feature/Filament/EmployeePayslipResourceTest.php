<?php

use App\Filament\Employee\Resources\Payslips\Pages\ManagePayslips;
use App\Models\Account;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRate;
use App\Models\SalaryStructure;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\PayrollRunService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('employee', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:Payslip', 'web'),
        Permission::findOrCreate('View:Payslip', 'web'),
    );
    Role::findOrCreate('payroll_accountant', 'web');

    Filament::setCurrentPanel(Filament::getPanel('employee'));

    PayrollRate::create(['type' => PayrollRate::TYPE_PROVIDENT_FUND, 'employee_contribution_percent' => 10, 'employer_contribution_percent' => 10, 'effective_from' => '2020-01-01']);
    PayrollRate::create(['type' => PayrollRate::TYPE_SOCIAL_SECURITY_FUND, 'employee_contribution_percent' => 11, 'employer_contribution_percent' => 20, 'effective_from' => '2020-01-01']);
    foreach ([TaxSlab::MARITAL_SINGLE, TaxSlab::MARITAL_MARRIED] as $status) {
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 0, 'upper_bound' => 500000, 'rate_percent' => 1, 'effective_from' => '2020-01-01']);
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 500000, 'upper_bound' => null, 'rate_percent' => 2, 'effective_from' => '2020-01-01']);
    }

    Company::create([
        'name' => 'Test Co',
        'salary_expense_account_id' => Account::factory()->expense()->create(['code' => '5100', 'name' => 'Salary Expense'])->id,
        'salary_payable_account_id' => Account::factory()->liability()->create(['code' => '2200', 'name' => 'Salary Payable'])->id,
        'statutory_payable_account_id' => Account::factory()->liability()->create(['code' => '2300', 'name' => 'Statutory Payable'])->id,
    ]);
});

test('an employee sees their own finalized payslip', function () {
    $payroll = User::factory()->create()->assignRole('payroll_accountant');
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id, 'hired_at' => '2020-01-01']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2020-01-01']);
    fullMonthAttendance($employee);

    $run = app(PayrollRunService::class)->run(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'), $payroll);
    app(PayrollRunService::class)->finalize($run, $payroll);

    $this->actingAs($user);

    Livewire::test(ManagePayslips::class)
        ->assertCanSeeTableRecords($run->fresh()->payslips);
});

test('a draft payslip is not visible to the employee — only finalized ones are', function () {
    $payroll = User::factory()->create()->assignRole('payroll_accountant');
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id, 'hired_at' => '2020-01-01']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2020-01-01']);

    $run = app(PayrollRunService::class)->run(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'), $payroll);

    $this->actingAs($user);

    Livewire::test(ManagePayslips::class)
        ->assertCanNotSeeTableRecords($run->payslips);
});

test('an employee never sees another employee\'s payslip', function () {
    $payroll = User::factory()->create()->assignRole('payroll_accountant');
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id, 'hired_at' => '2020-01-01']);

    $other = Employee::factory()->create(['hired_at' => '2020-01-01']);
    SalaryStructure::create(['employee_id' => $other->id, 'basic_salary' => 30000, 'effective_from' => '2020-01-01']);
    fullMonthAttendance($other);

    $run = app(PayrollRunService::class)->run(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'), $payroll);
    app(PayrollRunService::class)->finalize($run, $payroll);

    $this->actingAs($user);

    Livewire::test(ManagePayslips::class)
        ->assertCanNotSeeTableRecords($run->fresh()->payslips);
});

test('no create action exists — payslips are never made here', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ManagePayslips::class)
        ->assertActionDoesNotExist('create');
});
