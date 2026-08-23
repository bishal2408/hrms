<?php

use App\Filament\Resources\Accounts\Pages\ManageAccounts;
use App\Models\Account;
use App\Models\User;
use App\Services\JournalEntryService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * journal_lines.account_id and accounts.parent_account_id are both
 * restrictOnDelete() at the database level — these tests confirm the
 * Filament layer surfaces that as a clean notification (account untouched)
 * rather than letting the raw QueryException reach the user.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:Account', 'web'),
        Permission::findOrCreate('View:Account', 'web'),
        Permission::findOrCreate('Delete:Account', 'web'),
    );

    $this->accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($this->accountant);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('deleting an account with no journal history removes it', function () {
    $account = Account::factory()->create(['code' => '5999', 'name' => 'Unused Expense']);

    Livewire::test(ManageAccounts::class)
        ->callAction(TestAction::make('delete')->table($account));

    expect(Account::find($account->id))->toBeNull();
});

test('deleting an account with journal entry history is blocked and the account survives', function () {
    $cash = Account::factory()->create(['code' => '1000', 'name' => 'Cash']);
    $revenue = Account::factory()->revenue()->create(['code' => '4000', 'name' => 'Sales Revenue']);

    app(JournalEntryService::class)->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Cash sale',
        lines: [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
        ],
    );

    Livewire::test(ManageAccounts::class)
        ->callAction(TestAction::make('delete')->table($revenue));

    expect(Account::find($revenue->id))->not->toBeNull();
});

test('deleting a parent account with children is blocked and the parent survives', function () {
    $parent = Account::factory()->create(['code' => '1000', 'name' => 'Cash & Equivalents']);
    Account::factory()->create(['code' => '1001', 'name' => 'Petty Cash', 'parent_account_id' => $parent->id]);

    Livewire::test(ManageAccounts::class)
        ->callAction(TestAction::make('delete')->table($parent));

    expect(Account::find($parent->id))->not->toBeNull();
});
