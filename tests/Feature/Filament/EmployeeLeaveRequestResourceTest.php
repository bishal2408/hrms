<?php

use App\Filament\Employee\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\NepaliCalendar;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('employee', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:LeaveRequest', 'web'),
        Permission::findOrCreate('View:LeaveRequest', 'web'),
        Permission::findOrCreate('Create:LeaveRequest', 'web'),
    );

    Filament::setCurrentPanel(Filament::getPanel('employee'));
});

test('an employee can submit a leave request with BS dates, stored as AD', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);
    $type = LeaveType::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageLeaveRequests::class)
        ->callAction('create', data: [
            'leave_type_id' => $type->id,
            'start_date' => '2083-04-01',
            'end_date' => '2083-04-03',
            'reason' => 'Family event.',
        ])
        ->assertHasNoActionErrors();

    $request = LeaveRequest::sole();

    expect($request->start_date->toDateString())->toBe(NepaliCalendar::bsToAd('2083-04-01')->toDateString())
        ->and($request->days)->toBe(3)
        ->and($request->status)->toBe(LeaveRequest::STATUS_PENDING);
});

test('an end date before the start date is rejected, even given as BS strings', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);
    $type = LeaveType::factory()->create();

    $this->actingAs($user);

    Livewire::test(ManageLeaveRequests::class)
        ->callAction('create', data: [
            'leave_type_id' => $type->id,
            'start_date' => '2083-04-05',
            'end_date' => '2083-04-01',
            'reason' => 'Typo test.',
        ])
        ->assertHasActionErrors(['end_date']);
});

test('submitting beyond the remaining balance surfaces the service error, not a 500', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);
    $type = LeaveType::factory()->create(['default_entitlement_days' => 2]);

    $this->actingAs($user);

    Livewire::test(ManageLeaveRequests::class)
        ->assertOk()
        ->callAction('create', data: [
            'leave_type_id' => $type->id,
            'start_date' => '2083-04-01',
            'end_date' => '2083-04-10',
        ])
        ->assertOk();

    expect(LeaveRequest::count())->toBe(0);
});

test('an account with no linked employee cannot create a request', function () {
    $user = User::factory()->create()->assignRole('employee');

    $this->actingAs($user);

    Livewire::test(ManageLeaveRequests::class)
        ->assertActionHidden('create');
});

test('an employee only ever sees their own requests', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $other = Employee::factory()->create();

    $mine = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    $theirs = LeaveRequest::factory()->create(['employee_id' => $other->id]);

    $this->actingAs($user);

    Livewire::test(ManageLeaveRequests::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

test('an employee can cancel their own pending request from the list', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $request = LeaveRequest::factory()->create(['employee_id' => $employee->id]);

    $this->actingAs($user);

    Livewire::test(ManageLeaveRequests::class)
        ->callAction(TestAction::make('cancel')->table($request));

    expect($request->refresh()->status)->toBe(LeaveRequest::STATUS_CANCELLED);
});

test('the cancel button disappears once a request is no longer pending', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $request = LeaveRequest::factory()->approved()->create(['employee_id' => $employee->id]);

    $this->actingAs($user);

    Livewire::test(ManageLeaveRequests::class)
        ->assertActionHidden(TestAction::make('cancel')->table($request));
});
