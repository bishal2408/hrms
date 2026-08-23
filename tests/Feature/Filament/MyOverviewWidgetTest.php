<?php

use App\Filament\Employee\Widgets\MyOverviewWidget;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('employee', 'web');
    Filament::setCurrentPanel(Filament::getPanel('employee'));
});

test('an employee with nothing pending and no payslip yet sees the empty states', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(MyOverviewWidget::class)
        ->assertSee('Nothing waiting')
        ->assertSee('None yet');
});

test('pending requests only counts my own pending requests, not another employee\'s', function () {
    $user = User::factory()->create()->assignRole('employee');
    $mine = Employee::factory()->create(['user_id' => $user->id]);
    $stranger = Employee::factory()->create();

    $leaveType = LeaveType::factory()->create();
    LeaveRequest::factory()->create(['employee_id' => $mine->id, 'leave_type_id' => $leaveType->id, 'status' => LeaveRequest::STATUS_PENDING]);
    LeaveRequest::factory()->approved()->create(['employee_id' => $mine->id, 'leave_type_id' => $leaveType->id]); // not pending
    LeaveRequest::factory()->create(['employee_id' => $stranger->id, 'leave_type_id' => $leaveType->id, 'status' => LeaveRequest::STATUS_PENDING]); // not mine

    $this->actingAs($user);

    Livewire::test(MyOverviewWidget::class)
        ->assertSee('My pending requests')
        ->assertSee('1');
});

test('the latest finalized payslip shows net pay and period', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'net_pay' => 23680.25]);

    $this->actingAs($user);

    Livewire::test(MyOverviewWidget::class)
        ->assertSee('NPR 23,680.25')
        ->assertSee('Jul 2026');
});

test('a draft run\'s payslip is not shown as the latest payslip', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $draft = PayrollRun::factory()->create(); // status: draft
    Payslip::factory()->create(['payroll_run_id' => $draft->id, 'employee_id' => $employee->id, 'net_pay' => 99999]);

    $this->actingAs($user);

    Livewire::test(MyOverviewWidget::class)
        ->assertSee('None yet')
        ->assertDontSee('99,999');
});

test('a user with no employee record sees no stats at all', function () {
    $user = User::factory()->create()->assignRole('employee');
    $this->actingAs($user);

    Livewire::test(MyOverviewWidget::class)
        ->assertDontSee('My pending requests')
        ->assertDontSee('My latest payslip');
});
