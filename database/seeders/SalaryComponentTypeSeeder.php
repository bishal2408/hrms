<?php

namespace Database\Seeders;

use App\Models\SalaryComponentType;
use Illuminate\Database\Seeder;

class SalaryComponentTypeSeeder extends Seeder
{
    /**
     * Illustrative starting set, not a claim about what a real Nepali payslip
     * must contain — HR/payroll add, rename or remove these from Setup like
     * any other lookup table.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Transport Allowance', 'code' => 'transport', 'component_type' => SalaryComponentType::TYPE_ALLOWANCE],
            ['name' => 'Dearness Allowance', 'code' => 'dearness', 'component_type' => SalaryComponentType::TYPE_ALLOWANCE],
            ['name' => 'Loan Repayment', 'code' => 'loan_repayment', 'component_type' => SalaryComponentType::TYPE_DEDUCTION],
        ];

        foreach ($types as $type) {
            SalaryComponentType::query()->updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
