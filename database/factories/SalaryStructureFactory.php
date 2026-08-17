<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryStructure>
 */
class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'basic_salary' => fake()->numberBetween(30000, 80000),
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
