<?php

use App\Filament\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Models\LeaveType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('an admin can create a leave type', function () {
    Livewire::test(ManageLeaveTypes::class)
        ->callAction('create', data: [
            'name' => 'Study Leave',
            'code' => 'study',
            'default_entitlement_days' => 5,
            'is_paid' => false,
        ])
        ->assertHasNoActionErrors();

    expect(LeaveType::where('code', 'study')->exists())->toBeTrue();
});
