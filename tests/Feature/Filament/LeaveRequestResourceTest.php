<?php

use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The admin approval surface. LeaveRequestServiceTest already covers the
 * approve/reject/cancel rules exhaustively — these tests check that the
 * Filament layer wires up to that service correctly (buttons visible/hidden
 * for the right people, the actions actually call it, errors surface as
 * notifications instead of 500s) rather than re-deriving the rules.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // hr_admin/manager ship with zero permissions until RolePermissionSeeder
    // runs (see CLAUDE.md). Granted directly here since the resource under
    // test — not the seeder — is what these tests exercise.
    foreach (['hr_admin', 'manager'] as $roleName) {
        Role::findOrCreate($roleName, 'web')->givePermissionTo(
            Permission::findOrCreate('ViewAny:LeaveRequest', 'web'),
            Permission::findOrCreate('View:LeaveRequest', 'web'),
        );
    }
    Role::findOrCreate('super_admin', 'web');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('hr admin sees every leave request, not just some', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    $requests = LeaveRequest::factory()->count(3)->create();

    Livewire::test(ManageLeaveRequests::class)
        ->assertCanSeeTableRecords($requests);
});

test('a manager sees only their direct reports\' requests', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
    $report = Employee::factory()->create(['manager_id' => $manager->id]);
    $stranger = Employee::factory()->create();

    $visible = LeaveRequest::factory()->create(['employee_id' => $report->id]);
    $hidden = LeaveRequest::factory()->create(['employee_id' => $stranger->id]);

    $this->actingAs($managerUser);

    Livewire::test(ManageLeaveRequests::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);
});

test('approving from the table actually approves it', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    $request = LeaveRequest::factory()->create();

    Livewire::test(ManageLeaveRequests::class)
        ->callAction(TestAction::make('approve')->table($request));

    expect($request->refresh()->status)->toBe(LeaveRequest::STATUS_APPROVED)
        ->and($request->decided_by)->toBe($hr->id);
});

test('rejecting from the table requires a reason and records it', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    $request = LeaveRequest::factory()->create();

    Livewire::test(ManageLeaveRequests::class)
        ->callAction(TestAction::make('reject')->table($request), data: ['reason' => 'No cover available that week.']);

    expect($request->refresh()->status)->toBe(LeaveRequest::STATUS_REJECTED)
        ->and($request->decision_note)->toBe('No cover available that week.');
});

test('the approve and reject buttons are hidden for a request that is already decided', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    $request = LeaveRequest::factory()->approved()->create();

    Livewire::test(ManageLeaveRequests::class)
        ->assertActionHidden(TestAction::make('approve')->table($request))
        ->assertActionHidden(TestAction::make('reject')->table($request));
});

// A stranger's request never appears in the manager's table at all — the
// scope test above already covers that. This is the case where the row IS
// visible (a manager's own record shows up under their own "visible to self"
// scope) but the button must still be hidden, since nobody may approve their
// own leave.
test('a manager cannot approve their own leave request either', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    $manager = Employee::factory()->create(['user_id' => $managerUser->id]);

    $ownRequest = LeaveRequest::factory()->create(['employee_id' => $manager->id]);

    $this->actingAs($managerUser);

    Livewire::test(ManageLeaveRequests::class)
        ->assertCanSeeTableRecords([$ownRequest])
        ->assertActionHidden(TestAction::make('approve')->table($ownRequest));
});

test('nobody may approve their own leave request from the admin panel either', function () {
    $hrUser = User::factory()->create()->assignRole('hr_admin');
    $hrEmployee = Employee::factory()->create(['user_id' => $hrUser->id]);
    $this->actingAs($hrUser);

    $ownRequest = LeaveRequest::factory()->create(['employee_id' => $hrEmployee->id]);

    Livewire::test(ManageLeaveRequests::class)
        ->assertActionHidden(TestAction::make('approve')->table($ownRequest))
        ->assertActionHidden(TestAction::make('reject')->table($ownRequest));
});
