<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(2, true),
            'account_type' => Account::TYPE_ASSET,
            'parent_account_id' => null,
            'is_active' => true,
        ];
    }

    public function liability(): static
    {
        return $this->state(fn (): array => ['account_type' => Account::TYPE_LIABILITY]);
    }

    public function equity(): static
    {
        return $this->state(fn (): array => ['account_type' => Account::TYPE_EQUITY]);
    }

    public function revenue(): static
    {
        return $this->state(fn (): array => ['account_type' => Account::TYPE_REVENUE]);
    }

    public function expense(): static
    {
        return $this->state(fn (): array => ['account_type' => Account::TYPE_EXPENSE]);
    }
}
