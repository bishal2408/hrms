<?php

use App\Filament\Pages\CompanySettings;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'hr_admin', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('an hr_admin can update the company profile', function () {
    $admin = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($admin);

    Livewire::test(CompanySettings::class)
        ->fillForm([
            'name' => 'Acme Pvt. Ltd.',
            'pan_number' => '123456789',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Company::current()->name)->toBe('Acme Pvt. Ltd.')
        ->and(Company::current()->pan_number)->toBe('123456789');
});

test('a plain employee cannot access company settings', function () {
    $employee = User::factory()->create()->assignRole('employee');
    $this->actingAs($employee);

    expect(CompanySettings::canAccess())->toBeFalse();

    Livewire::test(CompanySettings::class)->assertForbidden();
});
