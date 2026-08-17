<?php

namespace Database\Factories;

use App\Models\SalaryComponentType;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryStructureItem>
 */
class SalaryStructureItemFactory extends Factory
{
    protected $model = SalaryStructureItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salary_structure_id' => SalaryStructure::factory(),
            'salary_component_type_id' => SalaryComponentType::factory(),
            'amount' => fake()->numberBetween(1000, 10000),
        ];
    }
}
