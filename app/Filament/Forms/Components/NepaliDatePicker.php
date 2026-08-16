<?php

namespace App\Filament\Forms\Components;

use App\Services\NepaliCalendar;
use Closure;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Text input for entering a date in the Nepali (Bikram Sambat) calendar.
 * The bound model attribute stays AD in the database — this field only
 * translates at the form boundary (CLAUDE.md: "BS is a display/input
 * concern only"), via NepaliCalendar, the single shared conversion service.
 */
class NepaliDatePicker extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->placeholder('YYYY-MM-DD (BS)');

        $this->afterStateHydrated(function ($state) {
            if (blank($state)) {
                return;
            }

            $adDate = $state instanceof Carbon ? $state->toDateString() : (string) $state;

            $this->state(NepaliCalendar::adToBs($adDate));
        });

        $this->dehydrateStateUsing(function ($state) {
            if (blank($state)) {
                return null;
            }

            return NepaliCalendar::bsToAd($state)->toDateString();
        });

        $this->rule(static fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
            if (blank($value)) {
                return;
            }

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $fail('The :attribute must be a Nepali date in YYYY-MM-DD format.');

                return;
            }

            try {
                NepaliCalendar::bsToAd($value);
            } catch (Throwable) {
                $fail('The :attribute is not a valid Nepali date.');
            }
        });
    }
}
