<?php

use App\Filament\Pages\TrialBalance;
use App\Models\Account;
use App\Models\User;
use App\Services\JournalEntryService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web');
    Role::findOrCreate('manager', 'web');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('payroll_accountant can access the trial balance page', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    expect(TrialBalance::canAccess())->toBeTrue();
});

test('a manager cannot access the trial balance page', function () {
    $manager = User::factory()->create()->assignRole('manager');
    $this->actingAs($manager);

    expect(TrialBalance::canAccess())->toBeFalse();
});

test('the page shows correct totals for the posted ledger', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    $cash = Account::factory()->create(['code' => '1000', 'name' => 'Cash']);
    $revenue = Account::factory()->revenue()->create(['code' => '4000', 'name' => 'Sales Revenue']);

    app(JournalEntryService::class)->post(
        entryDate: Carbon::parse('2026-07-01'),
        description: 'Cash sale',
        lines: [
            ['account_id' => $cash->id, 'debit' => 1200, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1200],
        ],
        postedBy: $accountant,
    );

    Livewire::test(TrialBalance::class)
        ->assertSet('totalDebit', 1200.0)
        ->assertSet('totalCredit', 1200.0);
});
