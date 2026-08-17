<?php

namespace App\Filament\Infolists\Components;

use App\Services\NepaliCalendar;
use Filament\Infolists\Components\TextEntry;

/**
 * Infolist entry that renders an AD date column as Bikram Sambat, with the AD
 * value kept underneath as helper text.
 *
 * The view-page counterpart of NepaliDateColumn and NepaliDatePicker. Together
 * they are the only three places BS conversion touches the UI (CLAUDE.md;
 * DESIGN.md B1/B2).
 */
class NepaliDateEntry extends TextEntry
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->placeholder('Not set');

        $this->formatStateUsing(
            fn (mixed $state): ?string => blank($state) ? null : NepaliCalendar::adToBs($state)
        );

        $this->helperText(function (mixed $state): ?string {
            if (blank($state)) {
                return null;
            }

            return NepaliCalendar::normalizeAd($state).' AD';
        });
    }
}
