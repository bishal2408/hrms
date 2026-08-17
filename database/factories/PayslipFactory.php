<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employee_id' => Employee::factory(),
            'basic_salary' => 30000,
            'total_days' => 30,
            'unpaid_days' => 0,
            'basic_after_attendance' => 30000,
            'allowance_items' => [],
            'deduction_items' => [],
            'allowances_total' => 0,
            'deductions_total' => 0,
            'gross_pay' => 30000,
            'pf_employee' => 3000,
            'pf_employer' => 3000,
            'ssf_employee' => 3300,
            'ssf_employer' => 6000,
            'taxable_income' => 23700,
            'tds' => 19.75,
            'net_pay' => 23680.25,
        ];
    }
}
