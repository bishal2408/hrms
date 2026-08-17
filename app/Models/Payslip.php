<?php

namespace App\Models;

use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A frozen snapshot of one employee's pay for one PayrollRun's period — see
 * the migration for why every monetary field here is written once and never
 * recomputed. `PayrollRunService::createPayslip()` is the only place a row
 * is created; nothing updates one after the fact except appending a
 * PayslipAdjustment.
 */
#[Fillable([
    'payroll_run_id', 'employee_id', 'basic_salary', 'total_days', 'unpaid_days',
    'basic_after_attendance', 'allowance_items', 'deduction_items', 'allowances_total',
    'deductions_total', 'gross_pay', 'pf_employee', 'pf_employer', 'ssf_employee',
    'ssf_employer', 'taxable_income', 'tds', 'net_pay',
])]
class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'basic_after_attendance' => 'decimal:2',
            'allowance_items' => 'array',
            'deduction_items' => 'array',
            'allowances_total' => 'decimal:2',
            'deductions_total' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'pf_employee' => 'decimal:2',
            'pf_employer' => 'decimal:2',
            'ssf_employee' => 'decimal:2',
            'ssf_employer' => 'decimal:2',
            'taxable_income' => 'decimal:2',
            'tds' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<PayslipAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayslipAdjustment::class);
    }

    /** net_pay plus every adjustment since — the actual amount paid, not just what the calculation produced. */
    protected function adjustedNetPay(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (float) $this->net_pay + $this->adjustments->sum(fn (PayslipAdjustment $a): float => (float) $a->amount),
            2
        ));
    }
}
