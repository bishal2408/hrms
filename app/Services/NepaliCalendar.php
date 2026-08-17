<?php

namespace App\Services;

use Anuzpandey\LaravelNepaliDate\LaravelNepaliDate;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;

/**
 * Single point of contact for AD<->BS conversion and Nepali fiscal-year
 * math (see CLAUDE.md: all BS conversion must go through one shared
 * service). Wraps anuzpandey/laravel-nepali-date rather than hand-rolling
 * calendar data.
 */
class NepaliCalendar
{
    /** BS month number the Nepali fiscal year starts on (Shrawan). */
    public const FISCAL_YEAR_START_MONTH = 4;

    /** BS month number the Nepali fiscal year ends on (Ashadh). */
    public const FISCAL_YEAR_END_MONTH = 3;

    public static function adToBs(DateTimeInterface|string $adDate, string $format = 'Y-m-d'): string
    {
        return LaravelNepaliDate::from(self::normalizeAd($adDate), 'Y-m-d', 'en')->toNepaliDate($format, 'en');
    }

    /**
     * Reduce any AD value to the plain Y-m-d string the conversion library
     * requires.
     *
     * Callers hand us dates in whatever shape their layer produced: a Carbon
     * from a model cast, a raw 'Y-m-d H:i:s' column value, or the ISO-8601
     * string Eloquent's date cast emits from attributesToArray() — which is
     * what Filament fills an edit form with. Normalising here rather than at
     * each call site keeps that knowledge in one place.
     *
     * @throws InvalidFormatException When the value is not a parseable AD date.
     */
    public static function normalizeAd(DateTimeInterface|string $adDate): string
    {
        return $adDate instanceof DateTimeInterface
            ? $adDate->format('Y-m-d')
            : CarbonImmutable::parse($adDate)->toDateString();
    }

    public static function bsToAd(string $bsDate, string $format = 'Y-m-d'): CarbonImmutable
    {
        $ad = LaravelNepaliDate::from($bsDate, $format, 'np')->toEnglishDate('Y-m-d', 'en');

        return CarbonImmutable::createFromFormat('Y-m-d', $ad)->startOfDay();
    }

    public static function daysInBsMonth(int $bsMonth, int $bsYear): int
    {
        return LaravelNepaliDate::daysInMonth($bsMonth, $bsYear);
    }

    public static function daysInBsYear(int $bsYear): int
    {
        return LaravelNepaliDate::daysInYear($bsYear);
    }

    /**
     * The Nepali fiscal year (Shrawan 1 - Ashadh end) that a given AD date
     * falls in: its BS start/end year, a display label (e.g. "2082/83"),
     * and its AD start/end dates for querying date-range-based records.
     *
     * @return array{bs_start_year: int, bs_end_year: int, label: string, start_date: CarbonImmutable, end_date: CarbonImmutable}
     */
    public static function fiscalYearFor(DateTimeInterface|string $adDate): array
    {
        $bs = LaravelNepaliDate::from($adDate, 'Y-m-d', 'en')->toNepaliDateArray();
        $bsYear = (int) $bs->year;
        $bsMonth = (int) $bs->month;

        $fiscalStartYear = $bsMonth >= self::FISCAL_YEAR_START_MONTH ? $bsYear : $bsYear - 1;
        $fiscalEndYear = $fiscalStartYear + 1;

        $lastAsarDay = self::daysInBsMonth(self::FISCAL_YEAR_END_MONTH, $fiscalEndYear);

        return [
            'bs_start_year' => $fiscalStartYear,
            'bs_end_year' => $fiscalEndYear,
            'label' => sprintf('%d/%s', $fiscalStartYear, substr((string) $fiscalEndYear, -2)),
            'start_date' => self::bsToAd(sprintf('%04d-%02d-01', $fiscalStartYear, self::FISCAL_YEAR_START_MONTH)),
            'end_date' => self::bsToAd(sprintf('%04d-%02d-%02d', $fiscalEndYear, self::FISCAL_YEAR_END_MONTH, $lastAsarDay)),
        ];
    }

    /** The current Nepali fiscal year, as of now(). */
    public static function currentFiscalYear(): array
    {
        return self::fiscalYearFor(CarbonImmutable::now());
    }

    /**
     * The 12 BS month names, 1-indexed. Fixed calendar facts (unlike tax
     * rates or PF percentages) — not configurable data, so this lives as a
     * constant here rather than a lookup table.
     *
     * @return array<int, string>
     */
    public static function bsMonthOptions(): array
    {
        return [
            1 => 'Baishakh',
            2 => 'Jestha',
            3 => 'Ashadh',
            4 => 'Shrawan',
            5 => 'Bhadra',
            6 => 'Ashwin',
            7 => 'Kartik',
            8 => 'Mangsir',
            9 => 'Poush',
            10 => 'Magh',
            11 => 'Falgun',
            12 => 'Chaitra',
        ];
    }

    /**
     * The AD start/end dates of a given BS year/month — e.g. for
     * constructing a payroll period aligned to a real Nepali calendar month
     * rather than an arbitrary date range.
     *
     * @return array{start_date: CarbonImmutable, end_date: CarbonImmutable}
     */
    public static function bsMonthBounds(int $bsYear, int $bsMonth): array
    {
        $lastDay = self::daysInBsMonth($bsMonth, $bsYear);

        return [
            'start_date' => self::bsToAd(sprintf('%04d-%02d-01', $bsYear, $bsMonth)),
            'end_date' => self::bsToAd(sprintf('%04d-%02d-%02d', $bsYear, $bsMonth, $lastDay)),
        ];
    }
}
