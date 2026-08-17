<?php

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Employee;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'hr_admin', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('an admin can create a login account and the password is hashed', function () {
    Livewire::test(ManageUsers::class)
        ->callAction('create', data: [
            'name' => 'Bishal Lamichhane',
            'email' => 'bishal@example.test',
            'password' => 'a-real-password',
            'roles' => [Role::findByName('employee', 'web')->id],
        ])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'bishal@example.test')->sole();

    expect($user->password)->not->toBe('a-real-password')
        ->and(Hash::check('a-real-password', $user->password))->toBeTrue()
        ->and($user->hasRole('employee'))->toBeTrue();
});

test('a new account can reach the employee panel but not the admin panel', function () {
    Livewire::test(ManageUsers::class)
        ->callAction('create', data: [
            'name' => 'Self Service Only',
            'email' => 'selfservice@example.test',
            'password' => 'a-real-password',
            'roles' => [Role::findByName('employee', 'web')->id],
        ])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'selfservice@example.test')->sole();

    expect($user->canAccessPanel(Filament::getPanel('employee')))->toBeTrue()
        ->and($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

test('editing an account without touching the password leaves it unchanged', function () {
    $user = User::factory()->create(['name' => 'Original Name']);
    $originalHash = $user->password;

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($user), data: [
            'name' => 'Renamed',
            'password' => '',
        ])
        ->assertHasNoActionErrors();

    $user->refresh();

    expect($user->name)->toBe('Renamed')
        ->and($user->password)->toBe($originalHash);
});

test('setting a new password on edit replaces the old one', function () {
    $user = User::factory()->create();
    $originalHash = $user->password;

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($user), data: [
            'password' => 'a-brand-new-password',
        ])
        ->assertHasNoActionErrors();

    $user->refresh();

    expect($user->password)->not->toBe($originalHash)
        ->and(Hash::check('a-brand-new-password', $user->password))->toBeTrue();
});

test('the edit form never exposes the stored password hash', function () {
    $user = User::factory()->create(['name' => 'Someone']);

    Livewire::test(ManageUsers::class)
        ->mountAction(TestAction::make('edit')->table($user))
        ->assertActionDataSet(['name' => 'Someone'])
        ->assertDontSee($user->password);
});

test('an account that is linked to an employee shows the link', function () {
    $user = User::factory()->create();
    Employee::factory()->create(['user_id' => $user->id, 'employee_code' => 'EMP-0007']);

    Livewire::test(ManageUsers::class)->assertSee('EMP-0007');
});

// Privilege escalation: whoever can edit accounts must not be able to promote
// themselves. super_admin is a gate-level bypass, so granting it is equivalent
// to handing over the whole system.
test('a non super admin is not offered super_admin as an assignable role', function () {
    $this->actingAs(User::factory()->create()->assignRole('hr_admin'));

    $offered = UserResource::assignableRoles(Role::query())->pluck('name')->all();

    expect($offered)->not->toContain('super_admin')
        ->and($offered)->toContain('hr_admin')
        ->and($offered)->toContain('employee');
});

test('a super admin may still assign super_admin', function () {
    $offered = UserResource::assignableRoles(Role::query())->pluck('name')->all();

    expect($offered)->toContain('super_admin');
});

test('an account cannot delete itself', function () {
    $other = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($this->admin))
        ->assertActionVisible(TestAction::make('delete')->table($other));
});
