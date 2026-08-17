<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->lexify('type_????'),
            'default_entitlement_days' => 10,
            'is_paid' => true,
        ];
    }

    /** No fixed allowance — balance tracking does not apply. */
    public function unlimited(): static
    {
        return $this->state(fn (): array => ['default_entitlement_days' => null]);
    }
}
