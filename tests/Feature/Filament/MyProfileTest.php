<?php

use App\Filament\Employee\Pages\MyProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\NepaliCalendar;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('employee', 'web');

    Filament::setCurrentPanel(Filament::getPanel('employee'));
});

test('an employee sees their own record on the self-service profile', function () {
    $user = User::factory()->create()->assignRole('employee');
    $department = Department::factory()->create(['name' => 'Finance']);

    Employee::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Anita',
        'last_name' => 'Shrestha',
        'employee_code' => 'EMP-0001',
        'department_id' => $department->id,
        'hired_at' => NepaliCalendar::bsToAd('2080-04-01')->toDateString(),
    ]);

    $this->actingAs($user);

    Livewire::test(MyProfile::class)
        ->assertOk()
        ->assertSee('Anita Shrestha')
        ->assertSee('EMP-0001')
        ->assertSee('Finance')
        // Dates are shown in BS, like everywhere else in the app.
        ->assertSee('2080-04-01');
});

test('an account with no employee record is told what to do instead of seeing a blank page', function () {
    $user = User::factory()->create()->assignRole('employee');

    $this->actingAs($user);

    Livewire::test(MyProfile::class)
        ->assertOk()
        ->assertSee('No employee record yet')
        ->assertSee('Ask HR to link your account');
});

test('the profile is read only — it exposes no save action', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(MyProfile::class)
        ->assertOk()
        ->assertDontSee('wire:submit');
});
