<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use App\Services\NepaliCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new LeaveBalanceService;
});

test('remaining days is the entitlement minus approved days', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->create(['default_entitlement_days' => 10]);

    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => now(),
        'end_date' => now()->addDays(2), // 3 days
    ]);

    expect($this->service->remainingDays($employee, $type))->toBe(7);
});

test('a leave type with no fixed entitlement has no remaining figure at all', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->unlimited()->create();

    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
    ]);

    expect($this->service->remainingDays($employee, $type))->toBeNull();
});

test('pending and rejected requests do not count against the balance, only approved ones do', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->create(['default_entitlement_days' => 10]);

    LeaveRequest::factory()->create([ // pending
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => now(),
        'end_date' => now()->addDays(4), // 5 days
    ]);
    LeaveRequest::factory()->rejected()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => now(),
        'end_date' => now()->addDays(4),
    ]);

    expect($this->service->remainingDays($employee, $type))->toBe(10);
});

test('remaining balance never goes below zero', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->create(['default_entitlement_days' => 5]);

    // Two approved requests summing to more than the entitlement — a real
    // scenario if an admin approves via the admin panel without the
    // service's own balance guard (e.g. correcting a historical record).
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => now(), 'end_date' => now()->addDays(3), // 4 days
    ]);
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDays(10), 'end_date' => now()->addDays(11), // 2 days
    ]);

    expect($this->service->remainingDays($employee, $type))->toBe(0);
});

test('a request from a previous fiscal year does not count against the current balance', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->create(['default_entitlement_days' => 10]);

    $previousFiscalYearDate = NepaliCalendar::currentFiscalYear()['start_date']->subDay();

    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => $previousFiscalYearDate,
        'end_date' => $previousFiscalYearDate,
    ]);

    expect($this->service->remainingDays($employee, $type))->toBe(10);
});

test('balances are per employee — one employee using leave does not affect another', function () {
    $type = LeaveType::factory()->create(['default_entitlement_days' => 10]);
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();

    LeaveRequest::factory()->approved()->create([
        'employee_id' => $a->id, 'leave_type_id' => $type->id,
        'start_date' => now(), 'end_date' => now()->addDays(4), // 5 days
    ]);

    expect($this->service->remainingDays($a, $type))->toBe(5)
        ->and($this->service->remainingDays($b, $type))->toBe(10);
});
