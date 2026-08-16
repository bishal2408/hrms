<?php

use App\Models\TaxSlab;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('slabsFor returns the full bracket set of the latest applicable version, ordered low to high', function () {
    // Old version (2 brackets)
    TaxSlab::create(['marital_status' => 'single', 'lower_bound' => 0, 'upper_bound' => 400000, 'rate_percent' => 1, 'effective_from' => '2020-01-01']);
    TaxSlab::create(['marital_status' => 'single', 'lower_bound' => 400000, 'upper_bound' => null, 'rate_percent' => 2, 'effective_from' => '2020-01-01']);

    // New version (3 brackets), inserted out of order to prove sorting works
    TaxSlab::create(['marital_status' => 'single', 'lower_bound' => 600000, 'upper_bound' => null, 'rate_percent' => 5, 'effective_from' => '2024-01-01']);
    TaxSlab::create(['marital_status' => 'single', 'lower_bound' => 0, 'upper_bound' => 300000, 'rate_percent' => 1, 'effective_from' => '2024-01-01']);
    TaxSlab::create(['marital_status' => 'single', 'lower_bound' => 300000, 'upper_bound' => 600000, 'rate_percent' => 3, 'effective_from' => '2024-01-01']);

    $slabs = TaxSlab::slabsFor('single', '2025-01-01');

    expect($slabs)->toHaveCount(3)
        ->and($slabs->pluck('lower_bound')->map(fn ($v) => (float) $v)->all())->toBe([0.0, 300000.0, 600000.0])
        ->and($slabs->pluck('rate_percent')->map(fn ($v) => (float) $v)->all())->toBe([1.0, 3.0, 5.0]);
});

test('slabsFor does not mix marital statuses', function () {
    TaxSlab::create(['marital_status' => 'single', 'lower_bound' => 0, 'upper_bound' => null, 'rate_percent' => 1, 'effective_from' => '2024-01-01']);
    TaxSlab::create(['marital_status' => 'married', 'lower_bound' => 0, 'upper_bound' => null, 'rate_percent' => 2, 'effective_from' => '2024-01-01']);

    expect(TaxSlab::slabsFor('single', '2025-01-01'))->toHaveCount(1)
        ->and(TaxSlab::slabsFor('married', '2025-01-01'))->toHaveCount(1);
});

test('slabsFor returns an empty collection when nothing is effective yet', function () {
    TaxSlab::create(['marital_status' => 'single', 'lower_bound' => 0, 'upper_bound' => null, 'rate_percent' => 1, 'effective_from' => '2030-01-01']);

    expect(TaxSlab::slabsFor('single', '2025-01-01'))->toHaveCount(0);
});
