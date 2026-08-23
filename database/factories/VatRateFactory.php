<?php

namespace Database\Factories;

use App\Models\VatRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VatRate>
 */
class VatRateFactory extends Factory
{
    protected $model = VatRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate_percent' => 13,
            'effective_from' => '2020-01-01',
            'notes' => null,
        ];
    }
}
