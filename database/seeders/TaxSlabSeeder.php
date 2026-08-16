<?php

namespace Database\Seeders;

use App\Models\TaxSlab;
use Illuminate\Database\Seeder;

class TaxSlabSeeder extends Seeder
{
    /**
     * PLACEHOLDER slabs — deliberately round, obviously-fake numbers, not
     * real IRD figures. Real slabs must be confirmed against the current
     * fiscal year's budget before going live (see CLAUDE.md's Payroll
     * section).
     */
    public function run(): void
    {
        foreach ([TaxSlab::MARITAL_SINGLE, TaxSlab::MARITAL_MARRIED] as $maritalStatus) {
            TaxSlab::create([
                'marital_status' => $maritalStatus,
                'lower_bound' => 0,
                'upper_bound' => 500000,
                'rate_percent' => 1.00,
                'effective_from' => '2000-01-01',
                'notes' => 'PLACEHOLDER — replace with the verified current tax slab table.',
            ]);

            TaxSlab::create([
                'marital_status' => $maritalStatus,
                'lower_bound' => 500000,
                'upper_bound' => null,
                'rate_percent' => 2.00,
                'effective_from' => '2000-01-01',
                'notes' => 'PLACEHOLDER — replace with the verified current tax slab table.',
            ]);
        }
    }
}
