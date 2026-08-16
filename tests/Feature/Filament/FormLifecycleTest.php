<?php

use App\Filament\Pages\CompanySettings;
use App\Filament\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Models\Company;
use App\Models\LeaveType;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Every form must work on the edit path, not just on create — a form fills
 * from attributesToArray() when editing, which hands components a different
 * value shape than the one they were built with (DESIGN.md F9).
 *
 * The two forms carrying a NepaliDatePicker are covered by
 * NepaliDateRoundTripTest, which asserts the BS/AD round trip specifically.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('editing a leave type loads its stored values', function () {
    $leaveType = LeaveType::create([
        'name' => 'Sick Leave',
        'code' => 'SICK',
        'default_entitlement_days' => 12,
        'is_paid' => true,
    ]);

    Livewire::test(ManageLeaveTypes::class)
        ->mountAction(TestAction::make('edit')->table($leaveType))
        ->assertActionDataSet([
            'name' => 'Sick Leave',
            'code' => 'SICK',
            'default_entitlement_days' => 12,
            'is_paid' => true,
        ]);
});

test('a leave type can be edited and saved', function () {
    $leaveType = LeaveType::create([
        'name' => 'Sick Leave',
        'code' => 'SICK',
        'default_entitlement_days' => 12,
        'is_paid' => true,
    ]);

    Livewire::test(ManageLeaveTypes::class)
        ->callAction(TestAction::make('edit')->table($leaveType), data: [
            'default_entitlement_days' => 15,
        ])
        ->assertHasNoActionErrors();

    expect($leaveType->refresh()->default_entitlement_days)->toBe(15);
});

test('company settings loads the saved company back into its form', function () {
    Company::create([
        'name' => 'Himalaya Trading',
        'pan_number' => '123456789',
        'email' => 'accounts@example.test',
    ]);

    Livewire::test(CompanySettings::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => 'Himalaya Trading',
            'pan_number' => '123456789',
            'email' => 'accounts@example.test',
        ]);
});

test('company settings mounts before a company exists', function () {
    Livewire::test(CompanySettings::class)->assertOk();
});
