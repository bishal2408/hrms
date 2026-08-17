<?php

use App\Exceptions\AlreadyClockedInException;
use App\Exceptions\NotClockedInException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AttendanceService;
});

test('clocking in creates a record for today with no clock-out yet', function () {
    $employee = Employee::factory()->create();

    Carbon::setTestNow('2026-08-17 09:03:00');
    $log = $this->service->clockIn($employee);

    expect($log->date->toDateString())->toBe('2026-08-17')
        ->and($log->clock_in->toDateTimeString())->toBe('2026-08-17 09:03:00')
        ->and($log->clock_out)->toBeNull()
        ->and($log->is_open)->toBeTrue()
        ->and($log->source)->toBe(AttendanceLog::SOURCE_MANUAL);

    Carbon::setTestNow();
});

test('clocking in twice in the same day is rejected', function () {
    $employee = Employee::factory()->create();

    $this->service->clockIn($employee);

    $this->service->clockIn($employee);
})->throws(AlreadyClockedInException::class);

test('clocking out closes the open record and computes worked minutes', function () {
    $employee = Employee::factory()->create();

    Carbon::setTestNow('2026-08-17 09:00:00');
    $this->service->clockIn($employee);

    Carbon::setTestNow('2026-08-17 17:30:00');
    $log = $this->service->clockOut($employee);

    expect($log->clock_out->toDateTimeString())->toBe('2026-08-17 17:30:00')
        ->and($log->is_open)->toBeFalse()
        ->and($log->worked_minutes)->toBe(510); // 8h30m

    Carbon::setTestNow();
});

test('clocking out without having clocked in is rejected', function () {
    $employee = Employee::factory()->create();

    $this->service->clockOut($employee);
})->throws(NotClockedInException::class);

test('clocking out twice is rejected the second time', function () {
    $employee = Employee::factory()->create();

    $this->service->clockIn($employee);
    $this->service->clockOut($employee);

    $this->service->clockOut($employee);
})->throws(NotClockedInException::class);

// Single session per day is a Phase 2a decision — a second clock-in after
// already completing a full in/out cycle the same day is not supported yet.
test('clocking in again after already completing a session today is rejected', function () {
    $employee = Employee::factory()->create();

    $this->service->clockIn($employee);
    $this->service->clockOut($employee);

    $this->service->clockIn($employee);
})->throws(AlreadyClockedInException::class);

test('attendance for one employee does not block another on the same day', function () {
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();

    Carbon::setTestNow('2026-08-17 09:00:00');

    $logA = $this->service->clockIn($a);
    $logB = $this->service->clockIn($b);

    expect($logA->id)->not->toBe($logB->id)
        ->and(AttendanceLog::query()->onDate('2026-08-17')->count())->toBe(2);

    Carbon::setTestNow();
});

test('a still-open record from a previous day does not block clocking in today', function () {
    $employee = Employee::factory()->create();

    AttendanceLog::factory()->open()->create([
        'employee_id' => $employee->id,
        'date' => '2026-08-16',
        'clock_in' => '2026-08-16 09:00:00',
    ]);

    Carbon::setTestNow('2026-08-17 09:00:00');
    $log = $this->service->clockIn($employee);

    expect($log->date->toDateString())->toBe('2026-08-17');

    Carbon::setTestNow();
});
