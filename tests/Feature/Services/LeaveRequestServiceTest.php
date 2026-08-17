<?php

use App\Exceptions\LeaveBalanceExceededException;
use App\Exceptions\LeaveRequestForbiddenException;
use App\Exceptions\LeaveRequestNotPendingException;
use App\Exceptions\OverlappingLeaveRequestException;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Services\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'hr_admin', 'manager', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->service = new LeaveRequestService(new LeaveBalanceService);
});

test('submitting a request computes the inclusive day count', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->create();

    $request = $this->service->submit($employee, $type, '2026-08-20', '2026-08-22');

    expect($request->days)->toBe(3)
        ->and($request->status)->toBe(LeaveRequest::STATUS_PENDING);
});

test('a request exceeding the remaining balance is rejected', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->create(['default_entitlement_days' => 2]);

    $this->service->submit($employee, $type, '2026-08-20', '2026-08-25');
})->throws(LeaveBalanceExceededException::class);

test('a leave type with no fixed entitlement is never balance-blocked', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->unlimited()->create();

    $request = $this->service->submit($employee, $type, '2026-08-20', '2026-09-20');

    expect($request->days)->toBe(32);
});

test('overlapping the same employee\'s pending request is rejected', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->create();

    $this->service->submit($employee, $type, '2026-08-20', '2026-08-22');
    $this->service->submit($employee, $type, '2026-08-21', '2026-08-23');
})->throws(OverlappingLeaveRequestException::class);

test('overlapping a rejected or cancelled request is fine — it no longer holds the dates', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->create();

    LeaveRequest::factory()->rejected()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => '2026-08-20',
        'end_date' => '2026-08-22',
    ]);

    $request = $this->service->submit($employee, $type, '2026-08-21', '2026-08-22');

    expect($request->exists)->toBeTrue();
});

test('two different employees can request the same dates', function () {
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();
    $type = LeaveType::factory()->create();

    $this->service->submit($a, $type, '2026-08-20', '2026-08-22');
    $request = $this->service->submit($b, $type, '2026-08-20', '2026-08-22');

    expect($request->exists)->toBeTrue();
});

test('a manager can approve their direct report\'s request', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
    $report = Employee::factory()->create(['manager_id' => $manager->id]);

    $request = LeaveRequest::factory()->create(['employee_id' => $report->id]);

    $approved = $this->service->approve($request, $managerUser);

    expect($approved->status)->toBe(LeaveRequest::STATUS_APPROVED)
        ->and($approved->decided_by)->toBe($managerUser->id);
});

test('a manager cannot approve a request from someone who is not their direct report', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    Employee::factory()->create(['user_id' => $managerUser->id]);
    $stranger = Employee::factory()->create(); // no manager relationship

    $request = LeaveRequest::factory()->create(['employee_id' => $stranger->id]);

    $this->service->approve($request, $managerUser);
})->throws(LeaveRequestForbiddenException::class);

test('nobody may approve their own leave request, including HR', function () {
    $hrUser = User::factory()->create()->assignRole('hr_admin');
    $hrEmployee = Employee::factory()->create(['user_id' => $hrUser->id]);

    $request = LeaveRequest::factory()->create(['employee_id' => $hrEmployee->id]);

    $this->service->approve($request, $hrUser);
})->throws(LeaveRequestForbiddenException::class);

test('hr admin can approve anyone\'s request regardless of the reporting line', function () {
    $hrUser = User::factory()->create()->assignRole('hr_admin');
    $employee = Employee::factory()->create(); // no relation to HR at all

    $request = LeaveRequest::factory()->create(['employee_id' => $employee->id]);

    $approved = $this->service->approve($request, $hrUser);

    expect($approved->status)->toBe(LeaveRequest::STATUS_APPROVED);
});

test('rejecting requires a reason and records it', function () {
    $hrUser = User::factory()->create()->assignRole('hr_admin');
    $employee = Employee::factory()->create();
    $request = LeaveRequest::factory()->create(['employee_id' => $employee->id]);

    $rejected = $this->service->reject($request, $hrUser, 'Team is short-staffed that week.');

    expect($rejected->status)->toBe(LeaveRequest::STATUS_REJECTED)
        ->and($rejected->decision_note)->toBe('Team is short-staffed that week.');
});

test('rejecting without a reason is rejected', function () {
    $hrUser = User::factory()->create()->assignRole('hr_admin');
    $request = LeaveRequest::factory()->create();

    $this->service->reject($request, $hrUser, '');
})->throws(InvalidArgumentException::class);

test('a decided request cannot be decided again', function () {
    $hrUser = User::factory()->create()->assignRole('hr_admin');
    $request = LeaveRequest::factory()->approved()->create();

    $this->service->approve($request, $hrUser);
})->throws(LeaveRequestNotPendingException::class);

test('an employee can cancel their own pending request', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $request = LeaveRequest::factory()->create(['employee_id' => $employee->id]);

    $cancelled = $this->service->cancel($request, $user);

    expect($cancelled->status)->toBe(LeaveRequest::STATUS_CANCELLED);
});

test('an employee cannot cancel someone else\'s request', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);
    $other = Employee::factory()->create();
    $request = LeaveRequest::factory()->create(['employee_id' => $other->id]);

    $this->service->cancel($request, $user);
})->throws(LeaveRequestForbiddenException::class);

test('an approved request can no longer be cancelled', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $request = LeaveRequest::factory()->approved()->create(['employee_id' => $employee->id]);

    $this->service->cancel($request, $user);
})->throws(LeaveRequestNotPendingException::class);
