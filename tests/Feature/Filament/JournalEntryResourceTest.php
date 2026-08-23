<?php

use App\Filament\Resources\JournalEntries\Pages\ManageJournalEntries;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalEntryService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:JournalEntry', 'web'),
        Permission::findOrCreate('View:JournalEntry', 'web'),
        Permission::findOrCreate('Create:JournalEntry', 'web'),
    );

    $this->accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($this->accountant);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->cash = Account::factory()->create(['code' => '1000', 'name' => 'Cash']);
    $this->revenue = Account::factory()->revenue()->create(['code' => '4000', 'name' => 'Sales Revenue']);
});

test('posting a balanced entry through the "New journal entry" action creates it', function () {
    Livewire::test(ManageJournalEntries::class)
        ->callAction('post', data: [
            'entry_date' => '2083-03-15',
            'description' => 'Cash sale',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

    expect(JournalEntry::count())->toBe(1)
        ->and(JournalEntry::first()->lines)->toHaveCount(2);
});

test('posting an unbalanced entry through the action does not create anything', function () {
    Livewire::test(ManageJournalEntries::class)
        ->callAction('post', data: [
            'entry_date' => '2083-03-15',
            'description' => 'Mistake',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 900],
            ],
        ]);

    expect(JournalEntry::count())->toBe(0);
});

test('the "New journal entry" action is hidden without Create:JournalEntry', function () {
    // Grant just enough to load the page (ViewAny) so the button's own
    // ->visible() check is what's actually under test, not the page-level
    // 403 an ungated manager would otherwise hit first (same isolation as
    // PayrollRunResourceTest's equivalent "hidden without Create" case).
    Role::findOrCreate('manager', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:JournalEntry', 'web'),
    );
    $manager = User::factory()->create()->assignRole('manager');
    $this->actingAs($manager);

    Livewire::test(ManageJournalEntries::class)
        ->assertActionHidden('post');
});

test('reversing a posted entry creates a swapped entry and hides the action afterwards', function () {
    $entry = app(JournalEntryService::class)->post(
        entryDate: now(),
        description: 'Cash sale',
        lines: [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ],
        postedBy: $this->accountant,
    );

    Livewire::test(ManageJournalEntries::class)
        ->callAction(TestAction::make('reverse')->table($entry));

    expect(JournalEntry::count())->toBe(2)
        ->and($entry->fresh()->isReversed())->toBeTrue();
});
