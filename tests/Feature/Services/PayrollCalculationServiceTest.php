<?php

use App\Exceptions\MissingPayrollRateException;
use App\Exceptions\MissingSalaryStructureException;
use App\Exceptions\MissingTaxSlabException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRate;
use App\Models\SalaryComponentType;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureItem;
use App\Models\TaxSlab;
use App\Services\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PayrollCalculationService;

    // A full month, chosen for round day counts (30). Kept out of individual
    // tests so every test's "period" means the same 30 days.
    $this->periodStart = Carbon::parse('2026-07-01');
    $this->periodEnd = Carbon::parse('2026-07-30');

    PayrollRate::create([
        'type' => PayrollRate::TYPE_PROVIDENT_FUND,
        'employee_contribution_percent' => 10,
        'employer_contribution_percent' => 10,
        'effective_from' => '2020-01-01',
    ]);
    PayrollRate::create([
        'type' => PayrollRate::TYPE_SOCIAL_SECURITY_FUND,
        'employee_contribution_percent' => 11,
        'employer_contribution_percent' => 20,
        'effective_from' => '2020-01-01',
    ]);

    foreach ([TaxSlab::MARITAL_SINGLE, TaxSlab::MARITAL_MARRIED] as $status) {
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 0, 'upper_bound' => 500000, 'rate_percent' => 1, 'effective_from' => '2020-01-01']);
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 500000, 'upper_bound' => null, 'rate_percent' => 2, 'effective_from' => '2020-01-01']);
    }
});

/** Every day in the period gets an attendance record — nobody unpaid. */
function fullyAttended(Employee $employee, Carbon $start, Carbon $end): void
{
    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'clock_in' => $date->copy()->setTime(9, 0),
            'clock_out' => $date->copy()->setTime(17, 0),
        ]);
    }
}

test('a fully attended month with allowances, a deduction, PF, SSF and TDS all combine correctly', function () {
    $employee = Employee::factory()->create(['marital_status' => TaxSlab::MARITAL_SINGLE]);
    fullyAttended($employee, $this->periodStart, $this->periodEnd);

    $structure = SalaryStructure::create([
        'employee_id' => $employee->id,
        'basic_salary' => 50000,
        'effective_from' => '2026-01-01',
    ]);
    $transport = SalaryComponentType::factory()->create(['name' => 'Transport Allowance']);
    $loan = SalaryComponentType::factory()->deduction()->create(['name' => 'Loan Repayment']);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_type_id' => $transport->id, 'amount' => 3000]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_type_id' => $loan->id, 'amount' => 2000]);

    $result = $this->service->calculate($employee, $this->periodStart, $this->periodEnd);

    // Hand-computed: basic 50000 (0 unpaid days) + 3000 allowance = 53000 gross.
    // PF 50000*10%=5000 (both sides); SSF employee 50000*11%=5500, employer 50000*20%=10000.
    // Taxable = 53000-5000-5500 = 42500; annualised = 510000.
    // Annual tax = 500000*1% + 10000*2% = 5000 + 200 = 5200; monthly TDS = 5200/12 = 433.33.
    // Net = 53000 - 2000 (loan) - 5000 (pf) - 5500 (ssf) - 433.33 (tds) = 40066.67.
    expect($result->totalDays)->toBe(30)
        ->and($result->unpaidDays)->toBe(0)
        ->and($result->basicAfterAttendance)->toBe(50000.0)
        ->and($result->allowancesTotal)->toBe(3000.0)
        ->and($result->deductionsTotal)->toBe(2000.0)
        ->and($result->grossPay)->toBe(53000.0)
        ->and($result->pfEmployee)->toBe(5000.0)
        ->and($result->pfEmployer)->toBe(5000.0)
        ->and($result->ssfEmployee)->toBe(5500.0)
        ->and($result->ssfEmployer)->toBe(10000.0)
        ->and($result->taxableIncome)->toBe(42500.0)
        ->and($result->tds)->toBe(433.33)
        ->and($result->netPay)->toBe(40066.67);
});

test('unattended, unexplained days are deducted pro-rata from basic salary', function () {
    $employee = Employee::factory()->create();
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2026-01-01']);

    // Only the first 20 of the 30 days are attended; the rest are simply absent.
    for ($i = 0; $i < 20; $i++) {
        $date = $this->periodStart->copy()->addDays($i);
        AttendanceLog::factory()->create(['employee_id' => $employee->id, 'date' => $date->toDateString(), 'clock_in' => $date->copy()->setTime(9, 0)]);
    }

    $result = $this->service->calculate($employee, $this->periodStart, $this->periodEnd);

    // 30000 * (30-10)/30 = 20000.
    expect($result->unpaidDays)->toBe(10)
        ->and($result->basicAfterAttendance)->toBe(20000.0);
});

test('approved paid leave counts as a paid day; approved unpaid leave does not', function () {
    $employee = Employee::factory()->create();
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2026-01-01']);

    $paidType = LeaveType::factory()->create(['is_paid' => true]);
    $unpaidType = LeaveType::factory()->create(['is_paid' => false]);

    // Days 1-5: approved paid leave. Days 6-10: approved unpaid leave. Days
    // 11-30 (20 days): nothing at all — also unpaid, same as an absence.
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $paidType->id,
        'start_date' => $this->periodStart->toDateString(), 'end_date' => $this->periodStart->copy()->addDays(4)->toDateString(),
    ]);
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $unpaidType->id,
        'start_date' => $this->periodStart->copy()->addDays(5)->toDateString(), 'end_date' => $this->periodStart->copy()->addDays(9)->toDateString(),
    ]);

    $result = $this->service->calculate($employee, $this->periodStart, $this->periodEnd);

    // Paid: the 5 paid-leave days only. Unpaid: 5 unpaid-leave + 20 uncovered = 25.
    // 30000 * (30-25)/30 = 5000.0 — written as a float literal since PHP's /
    // returns int(5000) for an evenly-divisible int expression, and
    // basicAfterAttendance is always float (it's rounded).
    expect($result->unpaidDays)->toBe(25)
        ->and($result->basicAfterAttendance)->toBe(5000.0);
});

test('a pending leave request does not count as a paid day — only approved does', function () {
    $employee = Employee::factory()->create();
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2026-01-01']);

    $paidType = LeaveType::factory()->create(['is_paid' => true]);

    LeaveRequest::factory()->create([ // still pending, never approved
        'employee_id' => $employee->id, 'leave_type_id' => $paidType->id,
        'start_date' => $this->periodStart->toDateString(), 'end_date' => $this->periodStart->copy()->addDays(4)->toDateString(),
    ]);

    $result = $this->service->calculate($employee, $this->periodStart, $this->periodEnd);

    expect($result->unpaidDays)->toBe(30);
});

test('a day that is both attended and covered by approved paid leave is only counted once', function () {
    $employee = Employee::factory()->create();
    $shortStart = Carbon::parse('2026-08-01');
    $shortEnd = Carbon::parse('2026-08-03');
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 3000, 'effective_from' => '2026-01-01']);

    $paidType = LeaveType::factory()->create(['is_paid' => true]);

    // Day 1: both attended AND covered by approved leave (overlap).
    // Day 2: attended only. Day 3: neither.
    AttendanceLog::factory()->create(['employee_id' => $employee->id, 'date' => '2026-08-01', 'clock_in' => '2026-08-01 09:00:00']);
    AttendanceLog::factory()->create(['employee_id' => $employee->id, 'date' => '2026-08-02', 'clock_in' => '2026-08-02 09:00:00']);
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $paidType->id,
        'start_date' => '2026-08-01', 'end_date' => '2026-08-01',
    ]);

    $result = $this->service->calculate($employee, $shortStart, $shortEnd);

    // If the overlap were double-counted (naive addition instead of a set
    // union), this would come out to 0 unpaid days instead of the correct 1.
    expect($result->totalDays)->toBe(3)
        ->and($result->unpaidDays)->toBe(1);
});

test('a salary structure with no line items still calculates', function () {
    $employee = Employee::factory()->create();
    fullyAttended($employee, $this->periodStart, $this->periodEnd);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 25000, 'effective_from' => '2026-01-01']);

    $result = $this->service->calculate($employee, $this->periodStart, $this->periodEnd);

    expect($result->allowancesTotal)->toBe(0.0)
        ->and($result->deductionsTotal)->toBe(0.0)
        ->and($result->grossPay)->toBe(25000.0);
});

test('an employee with no salary structure cannot be paid', function () {
    $employee = Employee::factory()->create();

    $this->service->calculate($employee, $this->periodStart, $this->periodEnd);
})->throws(MissingSalaryStructureException::class);

test('a missing PF rate for the period fails loudly rather than paying 0%', function () {
    PayrollRate::query()->where('type', PayrollRate::TYPE_PROVIDENT_FUND)->delete();

    $employee = Employee::factory()->create();
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2026-01-01']);

    $this->service->calculate($employee, $this->periodStart, $this->periodEnd);
})->throws(MissingPayrollRateException::class);

test('a missing tax slab for the employee\'s marital status fails loudly', function () {
    TaxSlab::query()->where('marital_status', TaxSlab::MARITAL_MARRIED)->delete();

    $employee = Employee::factory()->create(['marital_status' => TaxSlab::MARITAL_MARRIED]);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2026-01-01']);

    $this->service->calculate($employee, $this->periodStart, $this->periodEnd);
})->throws(MissingTaxSlabException::class);

test('a superseded salary structure is not used once a newer one takes effect', function () {
    $employee = Employee::factory()->create();
    fullyAttended($employee, $this->periodStart, $this->periodEnd);

    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 20000, 'effective_from' => '2020-01-01']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 35000, 'effective_from' => '2026-06-01']);

    $result = $this->service->calculate($employee, $this->periodStart, $this->periodEnd);

    expect($result->basicSalary)->toBe(35000.0);
});

// Regression guard for a real bug this suite caught: SQLite stores a
// `date`-cast column as '2026-07-30 00:00:00' (no native DATE type), which
// string-compares as *greater than* a plain '2026-07-30' bound and silently
// drops that boundary day from a whereBetween()/'<=' range query. MySQL's
// native DATE column masked this entirely — it only ever surfaced under the
// SQLite test database. Fixed with whereDate() everywhere a date-only column
// is range-compared; this test pins the exact boundary that broke.
test('the last day of the period is not silently dropped from attendance or leave overlap checks', function () {
    $employee = Employee::factory()->create();
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2026-01-01']);

    // Attendance on the period's exact last day.
    AttendanceLog::factory()->create([
        'employee_id' => $employee->id,
        'date' => $this->periodEnd->toDateString(),
        'clock_in' => $this->periodEnd->copy()->setTime(9, 0),
    ]);

    // A separate employee whose approved paid leave starts exactly on the
    // period's last day — must still register as overlapping.
    $employee2 = Employee::factory()->create();
    SalaryStructure::create(['employee_id' => $employee2->id, 'basic_salary' => 30000, 'effective_from' => '2026-01-01']);
    $paidType = LeaveType::factory()->create(['is_paid' => true]);
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee2->id,
        'leave_type_id' => $paidType->id,
        'start_date' => $this->periodEnd->toDateString(),
        'end_date' => $this->periodEnd->copy()->addDays(2)->toDateString(),
    ]);

    $result1 = $this->service->calculate($employee, $this->periodStart, $this->periodEnd);
    $result2 = $this->service->calculate($employee2, $this->periodStart, $this->periodEnd);

    // Only the last day is covered for each — 29 of 30 days unpaid, not 30.
    expect($result1->unpaidDays)->toBe(29)
        ->and($result2->unpaidDays)->toBe(29);
});
