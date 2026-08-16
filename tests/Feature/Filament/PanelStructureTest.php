<?php

use App\Filament\Resources\LeaveTypes\LeaveTypeResource;
use App\Filament\Resources\PayrollRates\PayrollRateResource;
use App\Filament\Resources\TaxSlabs\TaxSlabResource;
use App\Filament\Widgets\SetupStatusOverview;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
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

test('the admin panel ships no scaffolding widgets', function () {
    $widgets = Filament::getPanel('admin')->getWidgets();

    expect($widgets)
        ->not->toContain(AccountWidget::class)
        ->not->toContain(FilamentInfoWidget::class);
});

test('every setup resource is grouped, sorted and given its own icon', function () {
    $resources = [
        PayrollRateResource::class,
        TaxSlabResource::class,
        LeaveTypeResource::class,
    ];

    foreach ($resources as $resource) {
        expect($resource::getNavigationGroup())->toBe('Setup')
            ->and($resource::getNavigationSort())->not->toBeNull()
            ->and($resource::getNavigationIcon())->not->toBeNull();
    }

    // An icon repeated across resources carries no information (DESIGN.md R2).
    $icons = array_map(fn (string $resource) => $resource::getNavigationIcon(), $resources);

    expect($icons)->toHaveCount(count(array_unique($icons, SORT_REGULAR)));
});

test("Shield's roles resource is relabelled into Administration", function () {
    expect(RoleResource::getNavigationGroup())->toBe('Administration')
        ->and(RoleResource::getNavigationLabel())->toBe('Roles & Permissions');
});

test('the employee panel exposes none of the admin resources', function () {
    expect(Filament::getPanel('employee')->getResources())->toBeEmpty();
});

test('both panels share one brand, primary colour and typeface', function () {
    $admin = Filament::getPanel('admin');
    $employee = Filament::getPanel('employee');

    expect($employee->getBrandName())->toBe($admin->getBrandName())
        ->and($employee->getFontFamily())->toBe($admin->getFontFamily())
        ->and($employee->getColors()['primary'])->toBe($admin->getColors()['primary']);
});

test('the admin dashboard renders end to end', function () {
    $this->get('/admin')->assertOk();
});

test('the dashboard reports real setup state instead of placeholder figures', function () {
    Livewire::test(SetupStatusOverview::class)
        ->assertOk()
        ->assertSee('Fiscal year')
        ->assertSee('PF & SSF rates')
        ->assertSee('Tax brackets')
        ->assertSee('Leave types');
});
