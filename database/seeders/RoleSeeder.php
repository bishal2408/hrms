<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Base roles from CLAUDE.md's access-control section. Resource-level
     * permissions get attached to these as each Filament Resource is built
     * (via `shield:generate`) — this seeder only guarantees the roles exist.
     */
    public function run(): void
    {
        foreach (['super_admin', 'hr_admin', 'payroll_accountant', 'manager', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
