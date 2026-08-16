<?php

use App\Models\PayrollRate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('currentFor returns the most recent rate effective on or before the given date', function () {
    PayrollRate::create([
        'type' => PayrollRate::TYPE_PROVIDENT_FUND,
        'employee_contribution_percent' => 10,
        'employer_contribution_percent' => 10,
        'effective_from' => '2020-01-01',
    ]);

    $updated = PayrollRate::create([
        'type' => PayrollRate::TYPE_PROVIDENT_FUND,
        'employee_contribution_percent' => 11,
        'employer_contribution_percent' => 11,
        'effective_from' => '2024-01-01',
    ]);

    expect(PayrollRate::currentFor(PayrollRate::TYPE_PROVIDENT_FUND, '2025-01-01')->id)->toBe($updated->id);
});

test('currentFor ignores rates that only take effect in the future', function () {
    $old = PayrollRate::create([
        'type' => PayrollRate::TYPE_PROVIDENT_FUND,
        'employee_contribution_percent' => 10,
        'employer_contribution_percent' => 10,
        'effective_from' => '2020-01-01',
    ]);

    PayrollRate::create([
        'type' => PayrollRate::TYPE_PROVIDENT_FUND,
        'employee_contribution_percent' => 15,
        'employer_contribution_percent' => 15,
        'effective_from' => '2099-01-01',
    ]);

    expect(PayrollRate::currentFor(PayrollRate::TYPE_PROVIDENT_FUND, '2025-01-01')->id)->toBe($old->id);
});

test('currentFor only matches the requested type', function () {
    PayrollRate::create([
        'type' => PayrollRate::TYPE_SOCIAL_SECURITY_FUND,
        'employee_contribution_percent' => 11,
        'employer_contribution_percent' => 20,
        'effective_from' => '2020-01-01',
    ]);

    expect(PayrollRate::currentFor(PayrollRate::TYPE_PROVIDENT_FUND, '2025-01-01'))->toBeNull();
});
