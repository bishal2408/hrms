<?php

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Marks every day in [$start, $end] as attended for an employee.
 * PayrollCalculationService only pays attended (or paid-leave) days — see
 * its class docblock — so a payslip nets to $0 without this, and
 * PayrollRunService::finalize()'s ledger posting then has nothing to post.
 * Defaults to 2026-07-01–2026-07-30, the fixed payroll period every payroll
 * test in this suite uses.
 */
function fullMonthAttendance(Employee $employee, ?Carbon $start = null, ?Carbon $end = null): void
{
    $start ??= Carbon::parse('2026-07-01');
    $end ??= Carbon::parse('2026-07-30');

    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
        AttendanceLog::factory()->create(['employee_id' => $employee->id, 'date' => $date->toDateString(), 'clock_in' => $date->copy()->setTime(9, 0)]);
    }
}

/**
 * ChartWidget::getData() is protected — called directly via reflection
 * rather than asserting on serialized JSON inside rendered HTML, which would
 * be a fragile way to check the actual computed figures.
 *
 * @return array<string, mixed>
 */
function chartData(object $widget): array
{
    $method = new ReflectionMethod($widget, 'getData');
    $method->setAccessible(true);

    return $method->invoke($widget);
}
