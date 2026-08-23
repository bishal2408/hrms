<?php

use App\Filament\Employee\Widgets\MyLeaveBalanceWidget;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('employee', 'web');
    Filament::setCurrentPanel(Filament::getPanel('employee'));
});

test('a leave type with no fixed entitlement is not shown — it is not balance-tracked', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);

    $tracked = LeaveType::factory()->create(['name' => 'Annual Leave', 'default_entitlement_days' => 10]);
    $untracked = LeaveType::factory()->unlimited()->create(['name' => 'Unpaid Leave']);

    $this->actingAs($user);

    Livewire::test(MyLeaveBalanceWidget::class)
        ->assertSee('Annual Leave')
        ->assertDontSee('Unpaid Leave');
});

test('remaining days reflects approved usage this fiscal year', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $leaveType = LeaveType::factory()->create(['name' => 'Sick Leave', 'default_entitlement_days' => 10]);
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'days' => 3,
    ]);

    $this->actingAs($user);

    Livewire::test(MyLeaveBalanceWidget::class)
        ->assertSee('Sick Leave')
        ->assertSee('7 of 10 days');
});

test('a user with no employee record sees no leave balance stats at all', function () {
    $user = User::factory()->create()->assignRole('employee');
    LeaveType::factory()->create();

    $this->actingAs($user);

    Livewire::test(MyLeaveBalanceWidget::class)
        ->assertDontSee('days');
});
