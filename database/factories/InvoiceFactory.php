<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 99999);

        return [
            'customer_id' => Customer::factory(),
            'fiscal_year' => 2082,
            'sequence' => $sequence,
            'invoice_number' => sprintf('INV-2082-%04d', $sequence),
            'issue_date' => fake()->date(),
            'subtotal' => 1000,
            'vat_amount' => 0,
            'total' => 1000,
            'status' => Invoice::STATUS_ISSUED,
            'created_by' => User::factory(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => Invoice::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => User::factory(),
            'cancellation_reason' => 'Test cancellation',
        ]);
    }
}
