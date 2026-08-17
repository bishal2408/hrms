<?php

namespace App\Services;

use App\Exceptions\AlreadyClockedInException;
use App\Exceptions\NotClockedInException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The clock-in/clock-out state machine for self-service attendance.
 *
 * This is the only place that writes an attendance row on the employee's own
 * behalf — one row per employee per calendar day, single session (Phase 2a
 * decision). HR corrections go straight through the admin resource's form
 * instead, since a correction is an edit to an existing row, not a state
 * transition.
 */
class AttendanceService
{
    /**
     * Start today's attendance record.
     *
     * @throws AlreadyClockedInException When a record for today already exists.
     */
    public function clockIn(Employee $employee): AttendanceLog
    {
        $today = Carbon::now()->toDateString();

        if (AttendanceLog::query()->forEmployee($employee)->onDate($today)->exists()) {
            throw new AlreadyClockedInException;
        }

        try {
            return AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'clock_in' => Carbon::now(),
                'source' => AttendanceLog::SOURCE_MANUAL,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests raced past the exists() check — the unique
            // constraint on (employee_id, date) is the real guard.
            throw new AlreadyClockedInException;
        }
    }

    /**
     * Close today's open attendance record.
     *
     * @throws NotClockedInException When there is no open record for today.
     */
    public function clockOut(Employee $employee): AttendanceLog
    {
        return DB::transaction(function () use ($employee): AttendanceLog {
            $log = AttendanceLog::query()
                ->forEmployee($employee)
                ->onDate(Carbon::now()->toDateString())
                ->whereNull('clock_out')
                ->lockForUpdate()
                ->first();

            if ($log === null) {
                throw new NotClockedInException;
            }

            $log->update(['clock_out' => Carbon::now()]);

            return $log->refresh();
        });
    }
}
