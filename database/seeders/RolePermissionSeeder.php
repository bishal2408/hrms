<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A sensible starting set of permissions per role.
 *
 * This is a **baseline, not a policy**: it is additive and idempotent, so
 * re-running it tops roles up without stripping anything an admin has granted
 * in Administration → Roles & Permissions. Tune from the UI; this seeder only
 * guarantees a usable starting point.
 *
 * `super_admin` is deliberately absent — it is a gate-level bypass and needs no
 * permission rows.
 *
 * Requires `php artisan shield:generate` to have run for the panel, which is
 * what creates the permission rows this seeder attaches.
 */
class RolePermissionSeeder extends Seeder
{
    /** Read-only access to a resource. */
    private const READ = ['ViewAny', 'View'];

    /** Read plus create/edit, but no deleting. */
    private const WRITE = ['ViewAny', 'View', 'Create', 'Update'];

    /** Everything a lookup table needs, including removal. */
    private const MANAGE = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    public function run(): void
    {
        foreach ($this->matrix() as $roleName => $entities) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role === null) {
                $this->command?->warn("Role [{$roleName}] not found — skipped.");

                continue;
            }

            $role->givePermissionTo($this->resolvePermissions($this->expand($entities), $roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Who gets what.
     *
     * Three deliberate omissions:
     * - Nobody but super_admin gets `Role` permissions. The Roles & Permissions
     *   screen can attach any permission to any role, so granting it is a path
     *   to full control of the system.
     * - Nobody gets `Delete:Employee`. Employment ends by setting a termination
     *   date, which keeps the person attached to historical payroll and leave
     *   records; the UI exposes no delete action for them either.
     * - Nobody gets `Update:LeaveRequest`. Approve/reject/cancel are custom
     *   Filament Actions, not the stock EditAction, so Shield's blanket
     *   permission check never runs for them — LeaveRequestService::canDecide()
     *   (and the ownership check in cancel()) is the real authorization.
     * - Nobody gets `Update:EmployeeDocument`. There is no edit action on a
     *   document anywhere (wrong file → delete and re-upload, not edit in
     *   place), so granting it would be inert.
     *
     * @return array<string, array<string, list<string>>>
     */
    private function matrix(): array
    {
        return [
            // Runs the people side of the business.
            'hr_admin' => [
                'Employee' => self::WRITE,
                'Department' => self::MANAGE,
                'Designation' => self::MANAGE,
                'LeaveType' => self::MANAGE,
                // No "cancel don't delete" constraint here (that rule is
                // specifically for posted financial documents) — HR may
                // remove a duplicate or mistaken punch outright.
                'AttendanceLog' => self::MANAGE,
                // Just enough to open the resource — approve/reject is not
                // gated by these permissions at all. Both are custom Actions
                // (not the stock EditAction Shield's policy would intercept),
                // so LeaveRequestService::canDecide() is what actually decides
                // who may act on a given request. Granting Update here would
                // be inert: nothing checks it.
                'LeaveRequest' => self::READ,
                // Onboarding staff means creating their logins. Not delete —
                // removing an account should be a deliberate super_admin act.
                'User' => self::WRITE,
                'CompanySettings' => ['View'],
                'SetupStatusOverview' => ['View'],
                // HR owns the document-type lookup, same tier as
                // SalaryComponentType below.
                'DocumentType' => self::MANAGE,
                // Sensitive PII (citizenship copies, contracts) — see
                // EmployeeDocumentDownloadController's docblock: this is
                // deliberately narrower than Employee's own visibility.
                // Managers who can see a direct report's basic record do
                // NOT get this (decision: 2026-08-19).
                'EmployeeDocument' => ['ViewAny', 'View', 'Create', 'Delete', 'DeleteAny'],
            ],

            // Owns payroll and tax configuration; reads people data to run it.
            'payroll_accountant' => [
                'PayrollRate' => self::MANAGE,
                'TaxSlab' => self::MANAGE,
                // Lookup table with a real Delete action in its table, like
                // PayrollRate/TaxSlab — MANAGE fits.
                'SalaryComponentType' => self::MANAGE,
                // WRITE, not MANAGE: SalaryStructureResource exposes no
                // delete action at all (a pay change is a new effective-dated
                // row, never a removal — see the resource's docblock), so
                // Delete/DeleteAny would be granted-but-inert.
                'SalaryStructure' => self::WRITE,
                // Every verb here is genuinely checked (unlike LeaveRequest's
                // inert Update): Create gates "Run Payroll", Update gates
                // Recalculate/Finalize/Add adjustment, Delete gates Discard —
                // all explicit ->visible() checks on custom Actions, since
                // none of those are stock actions Shield's policy auto-wires.
                'PayrollRun' => self::MANAGE,
                'Employee' => self::READ,
                'Department' => self::READ,
                'Designation' => self::READ,
                // Read only — worked hours feed the payroll calculation
                // (Phase 3), but correcting a punch is HR's job, not theirs.
                'AttendanceLog' => self::READ,
                // Payslips and statutory filings need the company's PAN/VAT.
                'CompanySettings' => ['View'],
                'SetupStatusOverview' => ['View'],
                // Read only, like their Employee grant above — verifying
                // identity documents for statutory filings, not managing
                // uploads (that stays hr_admin's job).
                'EmployeeDocument' => self::READ,
            ],

            // Read-only on people. Which people is decided by
            // Employee::scopeVisibleTo() — their own record plus direct reports.
            // Deliberately not Update: that would also let them edit their own
            // hire date, employee code and (from Phase 3) salary.
            'manager' => [
                'Employee' => self::READ,
                'Department' => self::READ,
                'Designation' => self::READ,
                // Same reasoning as hr_admin's grant above — enough to open
                // the resource; LeaveRequestService::canDecide() (manager of
                // record + not their own request) governs the actual approve
                // /reject buttons.
                'LeaveRequest' => self::READ,
            ],

            // The admin panel is closed to this role entirely
            // (User::canAccessPanel), so everything below only ever applies on
            // the employee panel's own self-service LeaveRequestResource.
            // Create is real here: it gates the "New leave request" button via
            // Shield's policy on the *same* LeaveRequest model the admin
            // resource uses — Laravel policies are per-model, not per-panel.
            // That's safe only because query/route-binding scope every
            // employee to their own records (LeaveRequestResource::
            // getEloquentQuery()), and the admin panel they could never reach.
            'employee' => [
                'LeaveRequest' => ['ViewAny', 'View', 'Create'],
                // Payslip resource is entirely read-only (canCreate() is
                // hard-coded false; there is no edit or delete action at
                // all), scoped to the employee's own finalized payslips by
                // PayslipResource::getEloquentQuery() — same reasoning as
                // LeaveRequest above.
                'Payslip' => self::READ,
                // Same shape as Payslip: EmployeeDocumentResource is
                // read-only (canCreate() hard-coded false), scoped to the
                // employee's own documents by its own getEloquentQuery(). The
                // download route itself does its own separate ownership
                // check (EmployeeDocumentDownloadController) since it's a
                // plain URL, not Livewire-gated.
                'EmployeeDocument' => self::READ,
            ],
        ];
    }

    /**
     * Turn ['Employee' => ['ViewAny', 'View']] into ['ViewAny:Employee', ...].
     *
     * @param  array<string, list<string>>  $entities
     * @return list<string>
     */
    private function expand(array $entities): array
    {
        $names = [];

        foreach ($entities as $entity => $verbs) {
            foreach ($verbs as $verb) {
                $names[] = "{$verb}:{$entity}";
            }
        }

        return $names;
    }

    /**
     * Look the permissions up rather than creating them: a name that does not
     * exist means `shield:generate` has not been run for a new resource, which
     * is worth surfacing instead of silently inventing a permission nothing
     * checks.
     *
     * @param  list<string>  $names
     * @return Collection<int, Permission>
     */
    private function resolvePermissions(array $names, string $roleName)
    {
        $found = Permission::whereIn('name', $names)->where('guard_name', 'web')->get();

        $missing = array_diff($names, $found->pluck('name')->all());

        if ($missing !== []) {
            $this->command?->warn(
                "[{$roleName}] missing permissions (run shield:generate): ".implode(', ', $missing)
            );
        }

        return $found;
    }
}
