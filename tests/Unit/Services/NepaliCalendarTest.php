<?php

use App\Services\NepaliCalendar;

// Nepali New Year is a publicly documented fixed reference point — used
// here to pin the conversion instead of trusting round-trips alone.
test('converts the documented 2080 BS Nepali New Year correctly', function () {
    expect(NepaliCalendar::adToBs('2023-04-14'))->toBe('2080-01-01');
    expect(NepaliCalendar::bsToAd('2080-01-01')->toDateString())->toBe('2023-04-14');
});

test('AD to BS and back round-trips to the original date', function (string $adDate) {
    $bs = NepaliCalendar::adToBs($adDate);

    expect(NepaliCalendar::bsToAd($bs)->toDateString())->toBe($adDate);
})->with([
    '2000-01-01',
    '2010-06-15',
    '2020-12-31',
    '2023-04-14',
    '2030-08-20',
]);

test('a fiscal year starts on Shrawan 1 and ends on the last day of Ashadh', function () {
    // 2023-04-14 is Baisakh 1, 2080 BS — still within FY 2079/80, which
    // started Shrawan 2079 (mid-July 2022) and runs to Ashadh end 2080
    // (mid-July 2023). The new fiscal year 2080/81 doesn't start until
    // Shrawan 2080, three months later.
    $fy = NepaliCalendar::fiscalYearFor('2023-04-14');

    expect($fy['bs_start_year'])->toBe(2079)
        ->and($fy['bs_end_year'])->toBe(2080)
        ->and($fy['label'])->toBe('2079/80')
        ->and(NepaliCalendar::adToBs($fy['start_date']->toDateString()))->toBe('2079-04-01')
        ->and(NepaliCalendar::adToBs($fy['end_date']->toDateString()))
        ->toBe(sprintf('2080-03-%02d', NepaliCalendar::daysInBsMonth(3, 2080)));
});

test('a date on Shrawan 1 belongs to the fiscal year that just started, not the previous one', function () {
    $shrawan1 = NepaliCalendar::bsToAd('2081-04-01')->toDateString();
    $ashadhEnd = NepaliCalendar::bsToAd(sprintf('2081-03-%02d', NepaliCalendar::daysInBsMonth(3, 2081)))->toDateString();

    expect(NepaliCalendar::fiscalYearFor($shrawan1)['bs_start_year'])->toBe(2081)
        ->and(NepaliCalendar::fiscalYearFor($ashadhEnd)['bs_start_year'])->toBe(2080);
});

test('a BS month\'s AD bounds span the whole month and nothing else', function () {
    $bounds = NepaliCalendar::bsMonthBounds(2081, 4); // Shrawan 2081

    expect(NepaliCalendar::adToBs($bounds['start_date']->toDateString()))->toBe('2081-04-01')
        ->and(NepaliCalendar::adToBs($bounds['end_date']->toDateString()))
        ->toBe(sprintf('2081-04-%02d', NepaliCalendar::daysInBsMonth(4, 2081)))
        // The day after the month ends must fall into the next BS month.
        ->and(NepaliCalendar::adToBs($bounds['end_date']->addDay()->toDateString()))->toBe('2081-05-01');
});

test('all twelve BS months have a name', function () {
    expect(NepaliCalendar::bsMonthOptions())->toHaveCount(12)
        ->and(array_keys(NepaliCalendar::bsMonthOptions()))->toBe(range(1, 12));
});
