<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'employee_code',
    'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender',
    'marital_status', 'personal_email', 'phone', 'address',
    'pan_number', 'citizenship_number',
    'department_id', 'designation_id', 'manager_id', 'hired_at', 'terminated_at',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    use SoftDeletes;

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

    public const GENDER_OTHER = 'other';

    /**
     * Roles that may see every employee record. Everyone else is limited to
     * their own record plus whoever reports to them (CLAUDE.md: least
     * privilege; salary and identity data are sensitive).
     *
     * @var list<string>
     */
    public const ROLES_WITH_FULL_ACCESS = ['super_admin', 'hr_admin', 'payroll_accountant'];

    /** @return array<string, string> */
    public static function genderOptions(): array
    {
        return [
            self::GENDER_MALE => 'Male',
            self::GENDER_FEMALE => 'Female',
            self::GENDER_OTHER => 'Other',
        ];
    }

    /**
     * Deliberately delegated to TaxSlab: an employee's marital status selects
     * which TDS slab table applies, so the two value sets must never drift
     * apart. Change them in one place only.
     *
     * @return array<string, string>
     */
    public static function maritalStatusOptions(): array
    {
        return TaxSlab::maritalStatusOptions();
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hired_at' => 'date',
            'terminated_at' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Designation, $this> */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /** @return BelongsTo<self, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** @return HasMany<self, $this> */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /** @return HasMany<AttendanceLog, $this> */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** @return HasMany<SalaryStructure, $this> */
    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    /** @return HasMany<Payslip, $this> */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }

    /** Still employed — termination is what ends employment, not deletion. */
    protected function isActive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->terminated_at === null);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('terminated_at');
    }

    /**
     * Employees whose employment overlaps a date range at all — broader than
     * `active()`. Payroll for a period must include someone who was
     * terminated mid-period (they still worked part of it) and must exclude
     * someone hired after the period ended, regardless of today's active
     * status.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEmployedDuring(Builder $query, \DateTimeInterface|string $start, \DateTimeInterface|string $end): Builder
    {
        return $query
            ->whereDate('hired_at', '<=', $end)
            ->where(function (Builder $q) use ($start): void {
                $q->whereNull('terminated_at')->orWhereDate('terminated_at', '>=', $start);
            });
    }

    /**
     * Limit the query to what a given user is allowed to see.
     *
     * Visibility follows the reporting line rather than the role name: whoever
     * is recorded as a manager in the org chart can see their direct reports,
     * which stays correct even if role naming changes. Fails closed — a user
     * with no employee record and no privileged role sees nothing.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(self::ROLES_WITH_FULL_ACCESS)) {
            return $query;
        }

        $ownEmployeeId = static::query()->where('user_id', $user->id)->value('id');

        return $query->where(function (Builder $scoped) use ($user, $ownEmployeeId): void {
            $scoped->where('user_id', $user->id);

            if ($ownEmployeeId !== null) {
                $scoped->orWhere('manager_id', $ownEmployeeId);
            }
        });
    }
}
