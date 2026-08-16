<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'default_entitlement_days', 'is_paid'])]
class LeaveType extends Model
{
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
        ];
    }
}
