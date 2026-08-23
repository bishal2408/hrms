<?php

use App\Filament\Widgets\AttendanceTrendChart;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['hr_admin', 'manager'] as $roleName) {
        Role::findOrCreate($roleName, 'web')->givePermissionTo(
            Permission::findOrCreate('ViewAny:AttendanceLog', 'web'),
        );
    }
    Role::findOrCreate('employee', 'web');
});

test('canView requires ViewAny:AttendanceLog', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);
    expect(AttendanceTrendChart::canView())->toBeTrue();

    $employee = User::factory()->create()->assignRole('employee');
    $this->actingAs($employee);
    expect(AttendanceTrendChart::canView())->toBeFalse();
});

test('the chart covers exactly the last 14 days, oldest to newest', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    $data = chartData(new AttendanceTrendChart);

    expect($data['labels'])->toHaveCount(14)
        ->and($data['labels'][13])->toBe(Carbon::today()->format('M j'))
        ->and($data['labels'][0])->toBe(Carbon::today()->subDays(13)->format('M j'))
        ->and($data['datasets'][0]['data'])->toHaveCount(14);
});

test('a day with no attendance at all shows 0, not a missing entry', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    $data = chartData(new AttendanceTrendChart);

    expect($data['datasets'][0]['data'])->each->toBeInt();
});

test('present count reflects real attendance for today, scoped to the manager\'s reports', function () {
    $managerUser = User::factory()->create()->assignRole('manager');
    $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
    $report = Employee::factory()->create(['manager_id' => $manager->id]);
    $stranger = Employee::factory()->create(); // not this manager's report

    AttendanceLog::factory()->create(['employee_id' => $report->id, 'date' => Carbon::today()->toDateString()]);
    AttendanceLog::factory()->create(['employee_id' => $stranger->id, 'date' => Carbon::today()->toDateString()]);

    $this->actingAs($managerUser);

    $data = chartData(new AttendanceTrendChart);

    // Today is the last of the 14 labels — only the report's attendance
    // counts, the stranger's is invisible to this manager.
    expect($data['datasets'][0]['data'][13])->toBe(1);
});
