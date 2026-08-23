<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name', 'pan_number', 'vat_number', 'address', 'phone', 'email',
    'accounts_receivable_account_id', 'sales_revenue_account_id', 'vat_payable_account_id',
    'salary_expense_account_id', 'salary_payable_account_id', 'statutory_payable_account_id',
    'payroll_salary_calculation_mode',
])]
class Company extends Model
{
    /**
     * Default. `PayrollCalculationService::calculate()` pays basic salary
     * pro-rated by attendance/paid-leave days: an unattended, unapproved day
     * is unpaid.
     */
    public const PAYROLL_MODE_ATTENDANCE_PRORATED = 'attendance_prorated';

    /**
     * The full `basic_salary` is paid regardless of attendance — a day only
     * reduces pay if an approved, unpaid LeaveRequest explicitly covers it.
     * A mid-period hire/termination still prorates against the days actually
     * inside the employment window (see
     * PayrollCalculationService::fullSalaryPaidDayCount()) — this mode skips
     * attendance-based proration, not employment-window proration.
     */
    public const PAYROLL_MODE_FULL_SALARY = 'full_salary';

    /** This app manages a single company — there's only ever one row. */
    public static function current(): self
    {
        return static::query()->firstOrNew();
    }

    /** @return array<string, string> */
    public static function payrollSalaryCalculationModeOptions(): array
    {
        return [
            self::PAYROLL_MODE_ATTENDANCE_PRORATED => 'Pro-rated by attendance (default)',
            self::PAYROLL_MODE_FULL_SALARY => 'Full basic salary regardless of attendance',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function accountsReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accounts_receivable_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function salesRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sales_revenue_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function vatPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'vat_payable_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function salaryExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'salary_expense_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function salaryPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'salary_payable_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function statutoryPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'statutory_payable_account_id');
    }
}
