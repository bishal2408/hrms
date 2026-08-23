<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'debit' => 0,
            'credit' => 0,
            'description' => null,
        ];
    }

    public function debit(float $amount): static
    {
        return $this->state(fn (): array => ['debit' => $amount, 'credit' => 0]);
    }

    public function credit(float $amount): static
    {
        return $this->state(fn (): array => ['debit' => 0, 'credit' => $amount]);
    }
}
