<?php

use App\Filament\Resources\AttendanceLogs\Pages\ManageAttendanceLogs;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\User;
use App\Services\NepaliCalendar;
use Filament\Actions\Testing\TestAction;
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

test('an admin can add a corrected attendance record with BS dates, stored as AD', function () {
    $employee = Employee::factory()->create();

    Livewire::test(ManageAttendanceLogs::class)
        ->callAction('create', data: [
            'employee_id' => $employee->id,
            'date' => '2083-04-01',
            'source' => AttendanceLog::SOURCE_MANUAL,
            'clock_in' => '2026-07-17 09:00:00',
            'clock_out' => '2026-07-17 17:00:00',
        ])
        ->assertHasNoActionErrors();

    $log = AttendanceLog::sole();

    expect($log->date->toDateString())->toBe(NepaliCalendar::bsToAd('2083-04-01')->toDateString())
        ->and($log->worked_minutes)->toBe(480);
});

test('clock-out before clock-in is rejected', function () {
    $employee = Employee::factory()->create();

    Livewire::test(ManageAttendanceLogs::class)
        ->callAction('create', data: [
            'employee_id' => $employee->id,
            'date' => '2083-04-01',
            'source' => AttendanceLog::SOURCE_MANUAL,
            'clock_in' => '2026-07-17 17:00:00',
            'clock_out' => '2026-07-17 09:00:00',
        ])
        ->assertHasActionErrors(['clock_out']);
});

test('a second record for the same employee on the same BS date is rejected', function () {
    $employee = Employee::factory()->create();

    AttendanceLog::factory()->create([
        'employee_id' => $employee->id,
        'date' => NepaliCalendar::bsToAd('2083-04-01')->toDateString(),
    ]);

    Livewire::test(ManageAttendanceLogs::class)
        ->callAction('create', data: [
            'employee_id' => $employee->id,
            // Same calendar day, different BS string spelling — must still
            // resolve to the same AD date and be rejected.
            'date' => '2083-4-1',
            'source' => AttendanceLog::SOURCE_MANUAL,
            'clock_in' => '2026-07-17 09:00:00',
        ])
        ->assertHasActionErrors(['date']);
});

test('the same date is fine for two different employees', function () {
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();

    AttendanceLog::factory()->create([
        'employee_id' => $a->id,
        'date' => NepaliCalendar::bsToAd('2083-04-01')->toDateString(),
    ]);

    Livewire::test(ManageAttendanceLogs::class)
        ->callAction('create', data: [
            'employee_id' => $b->id,
            'date' => '2083-04-01',
            'source' => AttendanceLog::SOURCE_MANUAL,
            'clock_in' => '2026-07-17 09:00:00',
        ])
        ->assertHasNoActionErrors();
});

test('editing a record without changing its own date is not flagged as a duplicate of itself', function () {
    $employee = Employee::factory()->create();
    $log = AttendanceLog::factory()->create([
        'employee_id' => $employee->id,
        'date' => NepaliCalendar::bsToAd('2083-04-01')->toDateString(),
    ]);

    Livewire::test(ManageAttendanceLogs::class)
        ->callAction(TestAction::make('edit')->table($log), data: [
            'employee_id' => $employee->id,
            'date' => '2083-04-01',
            'source' => AttendanceLog::SOURCE_MANUAL,
            'clock_in' => $log->clock_in->toDateTimeString(),
            'notes' => 'Corrected the source.',
        ])
        ->assertHasNoActionErrors();

    expect($log->refresh()->notes)->toBe('Corrected the source.');
});

test('editing loads the stored AD date back as BS', function () {
    $log = AttendanceLog::factory()->create([
        'date' => NepaliCalendar::bsToAd('2083-04-01')->toDateString(),
    ]);

    Livewire::test(ManageAttendanceLogs::class)
        ->mountAction(TestAction::make('edit')->table($log))
        ->assertActionDataSet(['date' => '2083-04-01']);
});
