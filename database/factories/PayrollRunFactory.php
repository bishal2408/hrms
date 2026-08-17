<?php

namespace Database\Factories;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', 'now')->modify('first day of this month');

        return [
            'period_start' => $start->format('Y-m-d'),
            'period_end' => (clone $start)->modify('last day of this month')->format('Y-m-d'),
            'status' => PayrollRun::STATUS_DRAFT,
            'created_by' => User::factory(),
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => [
            'status' => PayrollRun::STATUS_FINALIZED,
            'calculated_at' => now(),
            'finalized_at' => now(),
            'finalized_by' => User::factory(),
        ]);
    }
}
