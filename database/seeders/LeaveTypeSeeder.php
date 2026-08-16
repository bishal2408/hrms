<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * PLACEHOLDER entitlement day counts — deliberately a flat, obviously-
     * fake number (10) across types, not real Labor Act 2074 figures. Real
     * entitlements must be confirmed before going live (see CLAUDE.md's
     * Leave section).
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Home/Annual Leave', 'code' => 'annual', 'default_entitlement_days' => 10, 'is_paid' => true],
            ['name' => 'Sick Leave', 'code' => 'sick', 'default_entitlement_days' => 10, 'is_paid' => true],
            ['name' => 'Public Holiday', 'code' => 'public_holiday', 'default_entitlement_days' => null, 'is_paid' => true],
            ['name' => 'Maternity Leave', 'code' => 'maternity', 'default_entitlement_days' => 10, 'is_paid' => true],
            ['name' => 'Paternity Leave', 'code' => 'paternity', 'default_entitlement_days' => 10, 'is_paid' => true],
            ['name' => 'Mourning (Kriya) Leave', 'code' => 'mourning', 'default_entitlement_days' => 10, 'is_paid' => true],
            ['name' => 'Leave Without Pay', 'code' => 'unpaid', 'default_entitlement_days' => null, 'is_paid' => false],
        ];

        foreach ($types as $type) {
            LeaveType::create($type);
        }
    }
}
