<?php

namespace Database\Factories;

use App\Models\SalaryComponentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryComponentType>
 */
class SalaryComponentTypeFactory extends Factory
{
    protected $model = SalaryComponentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->lexify('comp_????'),
            'component_type' => SalaryComponentType::TYPE_ALLOWANCE,
            'is_active' => true,
        ];
    }

    public function deduction(): static
    {
        return $this->state(fn (): array => ['component_type' => SalaryComponentType::TYPE_DEDUCTION]);
    }
}
