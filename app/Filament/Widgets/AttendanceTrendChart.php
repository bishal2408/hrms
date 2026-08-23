<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceLog;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Present-employee count for each of the last 14 days (DESIGN.md D2/D3 —
 * charts come after the stats row; D4 — dataviz skill invoked before writing
 * this). A line chart: one series, magnitude over a continuous short time
 * window — the idiomatic form for "trend," per the skill's form heuristic.
 *
 * D5: scoped through Employee::visibleTo($user), the same scope
 * OperationalOverview and EmployeeResource already use — a manager sees
 * their reports' attendance trend, not the whole company's.
 *
 * D6: one bounded, grouped query (14 days, GROUP BY date) — not 14 separate
 * per-day queries.
 *
 * Colour: Filament's own 'primary' token (the app's configured brand blue,
 * DESIGN.md P2) rather than a hand-picked hex — it already resolves
 * correctly in both themes and is the same colour every other primary
 * accent in the app uses. A single series needs no legend (the heading
 * names it) and no categorical palette, so the dataviz skill's multi-hue
 * validator doesn't apply here — nothing to validate.
 */
class AttendanceTrendChart extends ChartWidget
{
    protected static ?int $sort = 10;

    protected ?string $heading = 'Attendance — last 14 days';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:AttendanceLog') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return ['labels' => [], 'datasets' => []];
        }

        $days = collect(range(13, 0))->map(fn (int $offset): Carbon => Carbon::today()->subDays($offset));

        $presentByDate = AttendanceLog::query()
            ->whereDate('date', '>=', $days->first()->toDateString())
            ->whereDate('date', '<=', $days->last()->toDateString())
            ->whereHas('employee', fn (Builder $query) => $query->visibleTo($user)->active())
            ->selectRaw('date, COUNT(DISTINCT employee_id) as present_count')
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->date)->toDateString() => (int) $row->present_count]);

        return [
            'labels' => $days->map(fn (Carbon $day): string => $day->format('M j'))->all(),
            'datasets' => [
                [
                    'label' => 'Present',
                    'data' => $days->map(fn (Carbon $day): int => $presentByDate->get($day->toDateString(), 0))->all(),
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'title' => ['display' => true, 'text' => 'Employees present'],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false], // one series — the heading already names it
            ],
        ];
    }
}
