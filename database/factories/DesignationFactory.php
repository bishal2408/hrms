<?php

namespace Database\Factories;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'is_active' => true,
        ];
    }
}
