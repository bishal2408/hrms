<?php

namespace App\Filament\Tables\Columns;

use App\Services\NepaliCalendar;
use DateTimeInterface;
use Filament\Tables\Columns\TextColumn;

/**
 * Table column that renders an AD date column as Bikram Sambat, with the AD
 * value kept underneath as a secondary line.
 *
 * The display counterpart of NepaliDatePicker: conversion happens at the view
 * boundary only, and it happens here rather than in a formatStateUsing closure
 * copy-pasted into each resource (CLAUDE.md: BS conversion must not leak into
 * multiple places; DESIGN.md B1/B2).
 */
class NepaliDateColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(fn (mixed $state): ?string => static::toBs($state));

        $this->description(fn (mixed $state): ?string => static::toAd($state));
    }

    /** The stored AD value as a plain Y-m-d string, or null when empty. */
    protected static function toAd(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return $state instanceof DateTimeInterface
            ? $state->format('Y-m-d')
            : (string) $state;
    }

    /** The stored AD value rendered in BS, or null when empty. */
    protected static function toBs(mixed $state): ?string
    {
        $ad = static::toAd($state);

        return $ad === null ? null : NepaliCalendar::adToBs($ad);
    }
}
