<?php

use App\Exceptions\JournalEntryAlreadyReversedException;
use App\Exceptions\UnbalancedJournalEntryException;
use App\Models\Account;
use App\Models\User;
use App\Services\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new JournalEntryService;
    $this->cash = Account::factory()->create(['code' => '1000', 'name' => 'Cash', 'account_type' => Account::TYPE_ASSET]);
    $this->revenue = Account::factory()->revenue()->create(['code' => '4000', 'name' => 'Sales Revenue']);
    $this->poster = User::factory()->create();
});

test('a balanced entry posts with its lines', function () {
    $entry = $this->service->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Cash sale',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ],
        postedBy: $this->poster,
    );

    expect($entry->lines)->toHaveCount(2)
        ->and($entry->posted_by)->toBe($this->poster->id)
        ->and((float) $entry->lines[0]->debit)->toBe(1000.0)
        ->and((float) $entry->lines[1]->credit)->toBe(1000.0);
});

test('an entry that does not balance is rejected and nothing is written', function () {
    $this->service->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Mistake',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 900],
        ],
        postedBy: $this->poster,
    );
})->throws(UnbalancedJournalEntryException::class);

test('a line with both debit and credit set is rejected', function () {
    $this->service->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Bad line',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 1000],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 0],
        ],
        postedBy: $this->poster,
    );
})->throws(UnbalancedJournalEntryException::class);

test('a line with neither debit nor credit set is rejected', function () {
    $this->service->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Bad line',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 0],
        ],
        postedBy: $this->poster,
    );
})->throws(UnbalancedJournalEntryException::class);

test('fewer than two lines is rejected', function () {
    $this->service->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Just one line',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
        ],
        postedBy: $this->poster,
    );
})->throws(UnbalancedJournalEntryException::class);

test('reversing an entry posts a new entry with every line swapped', function () {
    $entry = $this->service->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Cash sale',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ],
        postedBy: $this->poster,
    );

    $reversal = $this->service->reverse($entry, $this->poster);
    $cashLine = $reversal->lines->firstWhere('account_id', $this->cash->id);
    $revenueLine = $reversal->lines->firstWhere('account_id', $this->revenue->id);

    expect($reversal->reverses_journal_entry_id)->toBe($entry->id)
        ->and((float) $cashLine->credit)->toBe(1000.0)
        ->and((float) $cashLine->debit)->toBe(0.0)
        ->and((float) $revenueLine->debit)->toBe(1000.0)
        ->and($entry->fresh()->isReversed())->toBeTrue();
});

test('an entry cannot be reversed twice', function () {
    $entry = $this->service->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Cash sale',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ],
        postedBy: $this->poster,
    );

    $this->service->reverse($entry, $this->poster);
    $this->service->reverse($entry, $this->poster);
})->throws(JournalEntryAlreadyReversedException::class);
