<?php

namespace App\Services;

use App\Exceptions\JournalEntryAlreadyReversedException;
use App\Exceptions\UnbalancedJournalEntryException;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only place a JournalEntry (and its lines) is written. A posted entry
 * is immutable — no edit or delete action exists anywhere (CLAUDE.md: no
 * hard deletes on posted financial records). A mistake is corrected with
 * `reverse()`, which posts a new entry with every line's debit/credit
 * swapped; the original is never touched, the same "never mutate, add a
 * correcting record" shape as PayslipAdjustment in the payroll domain.
 */
class JournalEntryService
{
    /**
     * @param  array<int, array{account_id: int, debit?: float|null, credit?: float|null, description?: string|null}>  $lines
     *
     * @throws UnbalancedJournalEntryException
     */
    public function post(
        Carbon $entryDate,
        string $description,
        array $lines,
        ?User $postedBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $reversesJournalEntryId = null,
    ): JournalEntry {
        $this->assertBalanced($lines);

        return DB::transaction(function () use ($entryDate, $description, $lines, $postedBy, $referenceType, $referenceId, $reversesJournalEntryId): JournalEntry {
            $entry = new JournalEntry([
                'entry_date' => $entryDate->toDateString(),
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reverses_journal_entry_id' => $reversesJournalEntryId,
            ]);
            $entry->posted_by = $postedBy?->id;
            $entry->save();

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    /**
     * @throws JournalEntryAlreadyReversedException
     */
    public function reverse(JournalEntry $entry, ?User $postedBy = null): JournalEntry
    {
        if ($entry->isReversed()) {
            throw new JournalEntryAlreadyReversedException("Journal entry #{$entry->id} has already been reversed.");
        }

        $entry->loadMissing('lines');

        $reversedLines = $entry->lines
            ->map(fn (JournalLine $line): array => [
                'account_id' => $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => $line->description,
            ])
            ->all();

        return $this->post(
            entryDate: now(),
            description: "Reversal of #{$entry->id}: {$entry->description}",
            lines: $reversedLines,
            postedBy: $postedBy,
            referenceType: $entry->reference_type,
            referenceId: $entry->reference_id,
            reversesJournalEntryId: $entry->id,
        );
    }

    /**
     * @param  array<int, array{account_id: int, debit?: float|null, credit?: float|null, description?: string|null}>  $lines
     *
     * @throws UnbalancedJournalEntryException
     */
    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new UnbalancedJournalEntryException('A journal entry needs at least two lines.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if (($debit > 0) === ($credit > 0)) {
                throw new UnbalancedJournalEntryException('Each journal line must have exactly one of debit or credit set.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new UnbalancedJournalEntryException(
                sprintf('Journal entry does not balance: debits %.2f, credits %.2f.', $totalDebit, $totalCredit)
            );
        }
    }
}
