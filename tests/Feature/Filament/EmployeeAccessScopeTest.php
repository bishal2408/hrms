<?php

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Employee records carry identity data, and from Phase 3 they carry salary
 * too. CLAUDE.md's rule is least privilege: HR sees everyone, a manager sees
 * their own record plus their direct reports, nobody else sees anything.
 *
 * These assert the scope itself rather than the absence of a link — hiding a
 * button is explicitly not access control.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'hr_admin', 'payroll_accountant', 'manager', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * A manager, someone reporting to them, and an unrelated employee.
 *
 * The manager role ships with no permissions (CLAUDE.md: roles start empty and
 * are granted through the Role resource), so the permissions needed to reach
 * the Employees screens are granted here explicitly. What is under test is the
 * record *scope*, not whether the role happens to be configured.
 *
 * @return array{manager: Employee, report: Employee, stranger: Employee}
 */
function orgChart(): array
{
    Role::findByName('manager', 'web')
        ->givePermissionTo(
            Permission::findOrCreate('ViewAny:Employee', 'web'),
            Permission::findOrCreate('View:Employee', 'web'),
            Permission::findOrCreate('Update:Employee', 'web'),
        );

    $managerUser = User::factory()->create()->assignRole('manager');

    $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
    $report = Employee::factory()->create(['manager_id' => $manager->id]);
    $stranger = Employee::factory()->create();

    return ['manager' => $manager, 'report' => $report, 'stranger' => $stranger];
}

test('hr admin sees every employee', function () {
    $org = orgChart();
    $hr = User::factory()->create()->assignRole('hr_admin');

    expect(Employee::query()->visibleTo($hr)->pluck('id')->sort()->values()->all())
        ->toBe(collect($org)->pluck('id')->sort()->values()->all());
});

test('a manager sees only their own record and their direct reports', function () {
    $org = orgChart();
    $managerUser = $org['manager']->user;

    $visible = Employee::query()->visibleTo($managerUser)->pluck('id')->all();

    expect($visible)->toContain($org['manager']->id)
        ->and($visible)->toContain($org['report']->id)
        ->and($visible)->not->toContain($org['stranger']->id);
});

test('a user with no employee record and no privileged role sees nobody', function () {
    orgChart();
    $outsider = User::factory()->create()->assignRole('employee');

    expect(Employee::query()->visibleTo($outsider)->count())->toBe(0);
});

test('the employee list only shows records the signed-in user may see', function () {
    $org = orgChart();
    $this->actingAs($org['manager']->user);

    Livewire::test(ListEmployees::class)
        ->assertCanSeeTableRecords([$org['manager'], $org['report']])
        ->assertCanNotSeeTableRecords([$org['stranger']]);
});

// Route-model binding is scoped too, so an out-of-scope id simply does not
// resolve. Laravel turns this into a 404 over HTTP.
test('a manager cannot open an unrelated employee by guessing the URL', function () {
    $org = orgChart();
    $this->actingAs($org['manager']->user);

    Livewire::test(EditEmployee::class, ['record' => $org['stranger']->getRouteKey()]);
})->throws(ModelNotFoundException::class);

// Global search is a second, easily-forgotten way to read records: it runs its
// own query, so it needs the same scope as the list.
test('global search does not leak employees outside the signed-in user scope', function () {
    $org = orgChart();
    $org['stranger']->update(['first_name' => 'Zebediah']);
    $org['report']->update(['first_name' => 'Zebediah']);

    $this->actingAs($org['manager']->user);

    $found = EmployeeResource::getGlobalSearchEloquentQuery()
        ->where('first_name', 'Zebediah')
        ->pluck('id')
        ->all();

    expect($found)->toContain($org['report']->id)
        ->and($found)->not->toContain($org['stranger']->id);
});

test('a manager can open their own direct report', function () {
    $org = orgChart();
    $this->actingAs($org['manager']->user);

    Livewire::test(EditEmployee::class, ['record' => $org['report']->getRouteKey()])
        ->assertOk();
});
