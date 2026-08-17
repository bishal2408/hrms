<?php

namespace Database\Factories;

use App\Models\PayslipAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayslipAdjustment>
 */
class PayslipAdjustmentFactory extends Factory
{
    protected $model = PayslipAdjustment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, -2000, 2000),
            'reason' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
