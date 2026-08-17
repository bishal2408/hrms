<?php

namespace App\Models;

use Database\Factories\AttendanceLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per employee per calendar day, holding that day's clock-in and
 * (once punched out) clock-out. `AttendanceService` is the only place that
 * writes to this model on the employee's own behalf — see it for the
 * clock-in/out state machine. HR may correct any field directly through the
 * admin resource.
 */
#[Fillable(['employee_id', 'date', 'clock_in', 'clock_out', 'source', 'notes'])]
class AttendanceLog extends Model
{
    /** @use HasFactory<AttendanceLogFactory> */
    use HasFactory;

    use SoftDeletes;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_BIOMETRIC = 'biometric';

    public const SOURCE_IMPORT = 'import';

    /** @return array<string, string> */
    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_MANUAL => 'Manual',
            self::SOURCE_BIOMETRIC => 'Biometric',
            self::SOURCE_IMPORT => 'Import',
        ];
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Minutes worked, or null while still clocked in. */
    protected function workedMinutes(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->clock_out === null
            ? null
            : $this->clock_in->diffInMinutes($this->clock_out));
    }

    /** Still clocked in — no clock-out recorded yet. */
    protected function isOpen(): Attribute
    {
        return Attribute::get(fn (): bool => $this->clock_out === null);
    }

    /** @param  Builder<self>  $query */
    public function scopeForEmployee(Builder $query, Employee $employee): Builder
    {
        return $query->where('employee_id', $employee->id);
    }

    /** @param  Builder<self>  $query */
    public function scopeOnDate(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query->whereDate('date', $date);
    }
}
