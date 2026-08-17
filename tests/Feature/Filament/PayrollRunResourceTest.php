<?php

use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Filament\Resources\PayrollRuns\RelationManagers\PayslipsRelationManager;
use App\Models\Employee;
use App\Models\PayrollRate;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\PayrollRunService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * PayrollRunServiceTest already covers the run/recalculate/finalize/discard
 * rules exhaustively — these check that the Filament layer wires up
 * correctly (buttons visible/hidden for the right permissions and states,
 * the "Run Payroll" action actually creates a run) rather than re-deriving
 * the service's own rules.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:PayrollRun', 'web'),
        Permission::findOrCreate('View:PayrollRun', 'web'),
        Permission::findOrCreate('Create:PayrollRun', 'web'),
        Permission::findOrCreate('Update:PayrollRun', 'web'),
        Permission::findOrCreate('Delete:PayrollRun', 'web'),
    );
    Role::findOrCreate('hr_admin', 'web');

    $this->payroll = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($this->payroll);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    PayrollRate::create(['type' => PayrollRate::TYPE_PROVIDENT_FUND, 'employee_contribution_percent' => 10, 'employer_contribution_percent' => 10, 'effective_from' => '2020-01-01']);
    PayrollRate::create(['type' => PayrollRate::TYPE_SOCIAL_SECURITY_FUND, 'employee_contribution_percent' => 11, 'employer_contribution_percent' => 20, 'effective_from' => '2020-01-01']);
    foreach ([TaxSlab::MARITAL_SINGLE, TaxSlab::MARITAL_MARRIED] as $status) {
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 0, 'upper_bound' => 500000, 'rate_percent' => 1, 'effective_from' => '2020-01-01']);
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 500000, 'upper_bound' => null, 'rate_percent' => 2, 'effective_from' => '2020-01-01']);
    }
});

test('running payroll from the list page creates a run and calculates payslips', function () {
    $employee = Employee::factory()->create(['hired_at' => '2020-01-01']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2020-01-01']);

    Livewire::test(ListPayrollRuns::class)
        ->callAction('runPayroll', data: [
            'bs_year' => 2083,
            'bs_month' => 4,
        ]);

    $run = PayrollRun::sole();

    expect($run->status)->toBe(PayrollRun::STATUS_DRAFT)
        ->and($run->payslips)->toHaveCount(1);
});

test('a user without Create:PayrollRun does not see the Run Payroll button', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    // hr_admin has no PayrollRun permissions at all — the page itself
    // (guarded by ViewAny) would 403, so grant just enough to load it and
    // isolate what's actually under test: the button's own visibility.
    Role::findByName('hr_admin', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:PayrollRun', 'web'),
    );

    Livewire::test(ListPayrollRuns::class)
        ->assertActionHidden('runPayroll');
});

test('recalculate, finalize and discard are visible on a draft run', function () {
    $employee = Employee::factory()->create(['hired_at' => '2020-01-01']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2020-01-01']);
    $run = app(PayrollRunService::class)->run(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'), $this->payroll);

    Livewire::test(ViewPayrollRun::class, ['record' => $run->getRouteKey()])
        ->assertActionVisible('recalculate')
        ->assertActionVisible('finalize')
        ->assertActionVisible('discard');
});

test('finalizing from the view page locks the run and hides the draft-only actions', function () {
    $employee = Employee::factory()->create(['hired_at' => '2020-01-01']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2020-01-01']);
    $run = app(PayrollRunService::class)->run(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'), $this->payroll);

    Livewire::test(ViewPayrollRun::class, ['record' => $run->getRouteKey()])
        ->callAction('finalize');

    expect($run->refresh()->status)->toBe(PayrollRun::STATUS_FINALIZED);

    Livewire::test(ViewPayrollRun::class, ['record' => $run->getRouteKey()])
        ->assertActionHidden('recalculate')
        ->assertActionHidden('finalize')
        ->assertActionHidden('discard');
});

test('add adjustment only appears once the run is finalized', function () {
    $employee = Employee::factory()->create(['hired_at' => '2020-01-01']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 30000, 'effective_from' => '2020-01-01']);
    $run = app(PayrollRunService::class)->run(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'), $this->payroll);
    $payslip = $run->payslips()->sole();

    Livewire::test(PayslipsRelationManager::class, ['ownerRecord' => $run, 'pageClass' => ViewPayrollRun::class])
        ->assertActionHidden(TestAction::make('addAdjustment')->table($payslip));

    app(PayrollRunService::class)->finalize($run, $this->payroll);

    Livewire::test(PayslipsRelationManager::class, ['ownerRecord' => $run->fresh(), 'pageClass' => ViewPayrollRun::class])
        ->assertActionVisible(TestAction::make('addAdjustment')->table($payslip));
});

// Not tested end-to-end through Livewire beyond this point: calling a
// schema-bearing (form-filling) Action hosted on a RelationManager throws
// inside Filament's own test harness — TestsForms::fillForm() reads
// $this->instance() twice in the same call and gets null the second time —
// confirmed (via a throwaway debug test, since removed) to be a framework
// testing-harness quirk specific to RelationManager + Action + schema, not a
// defect in this code: mountAction() alone succeeds, and the identical
// pattern (Action::make(...)->schema([...])->action(...)) already passes
// cleanly when hosted directly on a Resource page (see
// LeaveRequestResourceTest's reject test). The actual data flow —
// PayrollRunService::addAdjustment() persisting the row, guarding against a
// non-finalized run, and Payslip::adjustedNetPay reflecting it without
// touching the frozen figures — is fully covered at the service layer by
// PayrollRunServiceTest ("an adjustment changes the adjusted net pay...").
// What's left to the Filament layer is exactly what's tested above: the
// button is hidden on a draft and visible once finalized.
