<?php

use App\Filament\Employee\Pages\Attendance;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('employee', 'web');
    Filament::setCurrentPanel(Filament::getPanel('employee'));
});

test('an employee with no record yet sees the not-clocked-in state and a working button', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Attendance::class)
        ->assertOk()
        ->assertSee('Not clocked in today')
        ->call('clockIn')
        ->assertSee('Clocked in at');

    expect(AttendanceLog::sole()->is_open)->toBeTrue();
});

test('clocking out from the page closes the open record', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Carbon::setTestNow('2026-08-17 09:00:00');
    Livewire::test(Attendance::class)->call('clockIn');

    Carbon::setTestNow('2026-08-17 17:00:00');
    Livewire::test(Attendance::class)
        ->call('clockOut')
        ->assertSee('Clocked out at');

    expect(AttendanceLog::sole()->is_open)->toBeFalse();

    Carbon::setTestNow();
});

test('clocking in twice does not error and does not create a second record', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Attendance::class)
        ->call('clockIn')
        ->call('clockIn')
        ->assertOk();

    expect(AttendanceLog::count())->toBe(1);
});

test('an account with no employee record sees the explanatory message, not an error', function () {
    $user = User::factory()->create()->assignRole('employee');

    $this->actingAs($user);

    Livewire::test(Attendance::class)
        ->assertOk()
        ->assertSee('No employee record yet');
});

test('an account with no employee record cannot clock in', function () {
    $user = User::factory()->create()->assignRole('employee');

    $this->actingAs($user);

    Livewire::test(Attendance::class)->call('clockIn');

    expect(AttendanceLog::count())->toBe(0);
});

test('an employee only ever sees their own attendance history', function () {
    $userA = User::factory()->create()->assignRole('employee');
    $employeeA = Employee::factory()->create(['user_id' => $userA->id]);
    $employeeB = Employee::factory()->create();

    AttendanceLog::factory()->create(['employee_id' => $employeeA->id]);
    AttendanceLog::factory()->create(['employee_id' => $employeeB->id]);

    $this->actingAs($userA);

    $recent = Livewire::test(Attendance::class)->instance()->getRecentLogs();

    expect($recent)->toHaveCount(1)
        ->and($recent->first()->employee_id)->toBe($employeeA->id);
});
