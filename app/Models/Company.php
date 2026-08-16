<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'pan_number', 'vat_number', 'address', 'phone', 'email'])]
class Company extends Model
{
    /** This app manages a single company — there's only ever one row. */
    public static function current(): self
    {
        return static::query()->firstOrNew();
    }
}
