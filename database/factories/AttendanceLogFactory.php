<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    protected $model = AttendanceLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now');
        $clockIn = Carbon::instance($date)->setTime(9, 0);

        return [
            'employee_id' => Employee::factory(),
            'date' => $clockIn->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockIn->copy()->addHours(8),
            'source' => AttendanceLog::SOURCE_MANUAL,
        ];
    }

    /** Still clocked in — no clock-out yet. */
    public function open(): static
    {
        return $this->state(fn (): array => ['clock_out' => null]);
    }
}
