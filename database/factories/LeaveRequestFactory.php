<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '+1 month');

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
            'days' => 1,
            'reason' => fake()->sentence(),
            'status' => LeaveRequest::STATUS_PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => LeaveRequest::STATUS_APPROVED,
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => LeaveRequest::STATUS_REJECTED,
            'decided_at' => now(),
            'decision_note' => fake()->sentence(),
        ]);
    }
}
