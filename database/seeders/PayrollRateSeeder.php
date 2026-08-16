<?php

namespace Database\Seeders;

use App\Models\PayrollRate;
use Illuminate\Database\Seeder;

class PayrollRateSeeder extends Seeder
{
    /**
     * PLACEHOLDER rates — deliberately not realistic figures. Real PF/SSF
     * rates must be confirmed against current law before going live (see
     * CLAUDE.md's Payroll section).
     */
    public function run(): void
    {
        PayrollRate::create([
            'type' => PayrollRate::TYPE_PROVIDENT_FUND,
            'employee_contribution_percent' => 1.00,
            'employer_contribution_percent' => 1.00,
            'effective_from' => '2000-01-01',
            'notes' => 'PLACEHOLDER — replace with the verified current Provident Fund rate.',
        ]);

        PayrollRate::create([
            'type' => PayrollRate::TYPE_SOCIAL_SECURITY_FUND,
            'employee_contribution_percent' => 1.00,
            'employer_contribution_percent' => 1.00,
            'effective_from' => '2000-01-01',
            'notes' => 'PLACEHOLDER — replace with the verified current Social Security Fund rate.',
        ]);
    }
}
