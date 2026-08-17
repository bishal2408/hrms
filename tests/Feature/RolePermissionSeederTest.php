<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // Stand in for `shield:generate`, which creates these rows from the panel's
    // resources. The seeder attaches existing permissions, it never invents any.
    foreach (['Employee', 'Department', 'Designation', 'LeaveType', 'PayrollRate', 'TaxSlab', 'User', 'Role', 'AttendanceLog', 'LeaveRequest', 'SalaryComponentType', 'SalaryStructure', 'PayrollRun', 'Payslip'] as $entity) {
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $verb) {
            Permission::findOrCreate("{$verb}:{$entity}", 'web');
        }
    }
    Permission::findOrCreate('View:CompanySettings', 'web');
    Permission::findOrCreate('View:SetupStatusOverview', 'web');

    $this->seed(RolePermissionSeeder::class);
});

function userWithRole(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

test('hr admin can run the people side of the business', function () {
    $hr = userWithRole('hr_admin');

    expect($hr->can('Create:Employee'))->toBeTrue()
        ->and($hr->can('Update:Employee'))->toBeTrue()
        ->and($hr->can('DeleteAny:Department'))->toBeTrue()
        ->and($hr->can('Create:LeaveType'))->toBeTrue()
        ->and($hr->can('Create:User'))->toBeTrue()
        ->and($hr->can('View:CompanySettings'))->toBeTrue()
        // No cancel-don't-delete constraint on attendance — HR may remove a
        // duplicate or mistaken punch outright.
        ->and($hr->can('Delete:AttendanceLog'))->toBeTrue()
        // Enough to open the resource; approve/reject is governed by
        // LeaveRequestService::canDecide(), not this permission.
        ->and($hr->can('ViewAny:LeaveRequest'))->toBeTrue();
});

test('payroll accountant owns rates and slabs but cannot edit people', function () {
    $payroll = userWithRole('payroll_accountant');

    expect($payroll->can('Update:PayrollRate'))->toBeTrue()
        ->and($payroll->can('Update:TaxSlab'))->toBeTrue()
        ->and($payroll->can('ViewAny:Employee'))->toBeTrue()
        ->and($payroll->can('Update:Employee'))->toBeFalse()
        ->and($payroll->can('Create:Employee'))->toBeFalse()
        // Worked hours feed the payroll calculation (Phase 3); correcting a
        // punch is HR's job, not theirs.
        ->and($payroll->can('ViewAny:AttendanceLog'))->toBeTrue()
        ->and($payroll->can('Update:AttendanceLog'))->toBeFalse()
        // Payroll owns salary setup, same as PF/SSF/tax config.
        ->and($payroll->can('Delete:SalaryComponentType'))->toBeTrue()
        ->and($payroll->can('Create:SalaryStructure'))->toBeTrue()
        // No delete action exists on SalaryStructureResource — a pay change
        // is a new effective-dated row, not a removal — so this is granted
        // to nobody, including payroll_accountant, because nothing checks it.
        ->and($payroll->can('Delete:SalaryStructure'))->toBeFalse()
        // Unlike LeaveRequest, every PayrollRun verb is genuinely wired to a
        // custom action's ->visible() check.
        ->and($payroll->can('Create:PayrollRun'))->toBeTrue()
        ->and($payroll->can('Update:PayrollRun'))->toBeTrue()
        ->and($payroll->can('Delete:PayrollRun'))->toBeTrue();
});

test('hr admin has no access to salary structures — that is payroll\'s domain', function () {
    $hr = userWithRole('hr_admin');

    expect($hr->can('ViewAny:SalaryStructure'))->toBeFalse()
        ->and($hr->can('ViewAny:SalaryComponentType'))->toBeFalse()
        ->and($hr->can('ViewAny:PayrollRun'))->toBeFalse();
});

test('a manager gets read-only access to people', function () {
    $manager = userWithRole('manager');

    expect($manager->can('ViewAny:Employee'))->toBeTrue()
        ->and($manager->can('View:Employee'))->toBeTrue()
        // Update would also let them edit their own hire date, employee code
        // and — from Phase 3 — their own salary.
        ->and($manager->can('Update:Employee'))->toBeFalse()
        ->and($manager->can('Create:Employee'))->toBeFalse()
        ->and($manager->can('ViewAny:LeaveRequest'))->toBeTrue();
});

test('an employee gets nothing in the back office, but can reach their own leave requests', function () {
    $employee = userWithRole('employee');

    expect($employee->can('ViewAny:Employee'))->toBeFalse()
        ->and($employee->can('View:CompanySettings'))->toBeFalse()
        // These are the only grants this role has anywhere, and they only
        // matter on the employee panel — the admin panel is closed to this
        // role by User::canAccessPanel() regardless of what it can().
        ->and($employee->can('ViewAny:LeaveRequest'))->toBeTrue()
        ->and($employee->can('Create:LeaveRequest'))->toBeTrue()
        // Approve/reject/cancel are custom Actions, not the stock EditAction —
        // Update:LeaveRequest is granted to nobody because nothing checks it.
        ->and($employee->can('Update:LeaveRequest'))->toBeFalse()
        ->and($employee->can('ViewAny:Payslip'))->toBeTrue();
});

test('nobody but super admin can manage roles', function () {
    foreach (['hr_admin', 'payroll_accountant', 'manager', 'employee'] as $role) {
        expect(userWithRole($role)->can('Update:Role'))
            ->toBeFalse("[{$role}] must not be able to manage roles — it is a path to full control");
    }
});

test('no seeded role can delete an employee record', function () {
    foreach (['hr_admin', 'payroll_accountant', 'manager', 'employee'] as $role) {
        $user = userWithRole($role);

        expect($user->can('Delete:Employee'))->toBeFalse("[{$role}] should terminate, not delete")
            ->and($user->can('DeleteAny:Employee'))->toBeFalse();
    }
});

test('re-running the seeder is idempotent and keeps manual grants', function () {
    $hr = userWithRole('hr_admin');
    $role = Role::findByName('hr_admin', 'web');

    // Something an admin granted by hand in the UI.
    $role->givePermissionTo('Update:PayrollRate');
    $before = $role->permissions()->count();

    $this->seed(RolePermissionSeeder::class);

    expect($role->fresh()->permissions()->count())->toBe($before)
        ->and($hr->fresh()->can('Update:PayrollRate'))->toBeTrue();
});
