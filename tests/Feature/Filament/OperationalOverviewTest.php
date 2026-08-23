<?php

use App\Filament\Widgets\OperationalOverview;
use App\Models\Account;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Models\VatRate;
use App\Services\InvoiceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['hr_admin', 'manager'] as $roleName) {
        Role::findOrCreate($roleName, 'web')->givePermissionTo(
            Permission::findOrCreate('ViewAny:Employee', 'web'),
            Permission::findOrCreate('ViewAny:AttendanceLog', 'web'),
            Permission::findOrCreate('ViewAny:LeaveRequest', 'web'),
            Permission::findOrCreate('ViewAny:PayrollRun', 'web'),
        );
    }
    Role::findOrCreate('employee', 'web'); // no permissions at all
    Role::findOrCreate('payroll_accountant', 'web'); // gated by role, not permission — see OperationalOverview's docblock

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('headcount counts only active employees', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    Employee::factory()->count(2)->create();
    Employee::factory()->create(['terminated_at' => now()->subDay()]);

    Livewire::test(OperationalOverview::class)
        ->assertSee('Headcount')
        ->assertSee('2');
});

test('a manager\'s headcount is scoped to their direct reports, not the whole company', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
    Employee::factory()->count(2)->create(['manager_id' => $manager->id]);
    Employee::factory()->create(); // a stranger, not this manager's report

    $this->actingAs($managerUser);

    // 2 direct reports + the manager's own record = 3, never the full 4 —
    // not asserted via assertDontSee('4'): the rendered page is full of
    // stray digits (wire:key hashes, SVG path coordinates) that would make
    // that assertion pass or fail for reasons unrelated to this stat.
    Livewire::test(OperationalOverview::class)
        ->assertSee('Headcount')
        ->assertSee('3');
});

test('present today reflects real attendance for today only', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    $present = Employee::factory()->create();
    Employee::factory()->create(); // absent — no attendance logged today

    AttendanceLog::factory()->create([
        'employee_id' => $present->id,
        'date' => Carbon::today()->toDateString(),
        'clock_in' => now(),
    ]);

    Livewire::test(OperationalOverview::class)
        ->assertSee('Present today')
        ->assertSee('1 of 2');
});

test('pending leave approvals only counts pending status, scoped to the manager\'s reports', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
    $report = Employee::factory()->create(['manager_id' => $manager->id]);
    $stranger = Employee::factory()->create();

    $leaveType = LeaveType::factory()->create();
    LeaveRequest::factory()->create(['employee_id' => $report->id, 'leave_type_id' => $leaveType->id, 'status' => LeaveRequest::STATUS_PENDING]);
    LeaveRequest::factory()->approved()->create(['employee_id' => $report->id, 'leave_type_id' => $leaveType->id]); // not pending
    LeaveRequest::factory()->create(['employee_id' => $stranger->id, 'leave_type_id' => $leaveType->id, 'status' => LeaveRequest::STATUS_PENDING]); // not this manager's

    $this->actingAs($managerUser);

    Livewire::test(OperationalOverview::class)
        ->assertSee('Pending leave approvals')
        ->assertSee('1');
});

test('the payroll stat shows no runs yet, then the latest run\'s status once one exists', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    Livewire::test(OperationalOverview::class)
        ->assertSee('No runs yet');

    PayrollRun::factory()->create([
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-30',
    ]);

    Livewire::test(OperationalOverview::class)
        ->assertSee('Draft')
        ->assertDontSee('No runs yet');
});

test('on leave today reflects approved leave covering today, scoped to the manager\'s reports', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
    $report = Employee::factory()->create(['manager_id' => $manager->id]);
    $stranger = Employee::factory()->create();

    $leaveType = LeaveType::factory()->create();
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $report->id, 'leave_type_id' => $leaveType->id,
        'start_date' => Carbon::today()->subDay()->toDateString(), 'end_date' => Carbon::today()->addDay()->toDateString(),
    ]);
    LeaveRequest::factory()->approved()->create([ // covers today but not this manager's report
        'employee_id' => $stranger->id, 'leave_type_id' => $leaveType->id,
        'start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(),
    ]);
    LeaveRequest::factory()->approved()->create([ // this manager's report, but doesn't cover today
        'employee_id' => $report->id, 'leave_type_id' => $leaveType->id,
        'start_date' => Carbon::today()->addDays(5)->toDateString(), 'end_date' => Carbon::today()->addDays(6)->toDateString(),
    ]);

    $this->actingAs($managerUser);

    Livewire::test(OperationalOverview::class)
        ->assertSee('On leave today')
        ->assertSee('Approved and away today');
});

test('PF/SSF and TDS due show no runs yet until a run is finalized, then the period\'s totals', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    Livewire::test(OperationalOverview::class)
        ->assertSee('PF/SSF due')
        ->assertSee('TDS due')
        ->assertSeeInOrder(['PF/SSF due', 'No runs yet']);

    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create([
        'payroll_run_id' => $run->id,
        'pf_employee' => 3000, 'pf_employer' => 3000, 'ssf_employee' => 3300, 'ssf_employer' => 6000,
        'tds' => 433.33,
    ]);

    Livewire::test(OperationalOverview::class)
        ->assertSee('NPR 15,300.00') // pfSsfDue grand total
        ->assertSee('NPR 433.33'); // tdsDue
});

test('sales and VAT collected this month reflect real invoice data', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    Company::create([
        'name' => 'Test Co',
        'accounts_receivable_account_id' => Account::factory()->create(['code' => '1200'])->id,
        'sales_revenue_account_id' => Account::factory()->revenue()->create(['code' => '4000'])->id,
        'vat_payable_account_id' => Account::factory()->liability()->create(['code' => '2100'])->id,
    ]);
    VatRate::create(['rate_percent' => 13, 'effective_from' => '2020-01-01']);

    app(InvoiceService::class)->create(
        Customer::factory()->create(),
        Carbon::now(),
        [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'is_vatable' => true]],
        $accountant,
    );

    Livewire::test(OperationalOverview::class)
        ->assertSee('Sales this month')
        ->assertSee('NPR 1,000.00')
        ->assertSee('VAT collected this month')
        ->assertSee('NPR 130.00');
});

test('a manager without the payroll_accountant/super_admin role does not see the compliance or accounting stats', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    $this->actingAs($managerUser);

    Livewire::test(OperationalOverview::class)
        ->assertDontSee('PF/SSF due')
        ->assertDontSee('TDS due')
        ->assertDontSee('Sales this month')
        ->assertDontSee('VAT collected this month');
});

test('a user with none of the underlying permissions sees no stats at all', function () {
    $employee = User::factory()->create()->assignRole('employee');
    $this->actingAs($employee);

    Livewire::test(OperationalOverview::class)
        ->assertDontSee('Headcount')
        ->assertDontSee('Present today')
        ->assertDontSee('Pending leave approvals')
        ->assertDontSee('On leave today')
        ->assertDontSee('Latest payroll run')
        ->assertDontSee('No runs yet')
        ->assertDontSee('PF/SSF due')
        ->assertDontSee('TDS due')
        ->assertDontSee('Sales this month')
        ->assertDontSee('VAT collected this month');
});
