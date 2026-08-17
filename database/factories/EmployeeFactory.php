<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\TaxSlab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'marital_status' => fake()->randomElement([
                TaxSlab::MARITAL_SINGLE,
                TaxSlab::MARITAL_MARRIED,
            ]),
            'hired_at' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
        ];
    }

    /** An employee who has left the company. */
    public function terminated(): static
    {
        return $this->state(fn (): array => [
            'terminated_at' => now()->subMonth()->toDateString(),
        ]);
    }
}
