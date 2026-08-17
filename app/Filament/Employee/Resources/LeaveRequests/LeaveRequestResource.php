<?php

namespace App\Filament\Employee\Resources\LeaveRequests;

use App\Filament\Employee\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Filament\Forms\Components\NepaliDatePicker;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use App\Services\NepaliCalendar;
use BackedEnum;
use Closure;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Self-service: submit a leave request, watch it through the decision, cancel
 * it while still pending. There is no edit — see LeaveRequestService for why
 * changing dates on a live request isn't offered (the balance/overlap checks
 * are correct for a *new* request, not for editing one already counted).
 *
 * Scoped to the signed-in user's own employee record everywhere: the list
 * query, route-model binding, and the create action all key off it. An
 * account with no linked employee simply cannot create — see canCreate().
 */
class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $slug = 'leave-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance & Leave';

    protected static ?string $navigationLabel = 'Leave';

    protected static ?int $navigationSort = 20;

    /** @return Builder<LeaveRequest> */
    public static function getEloquentQuery(): Builder
    {
        $employee = auth()->user()?->employee;

        return $employee instanceof Employee
            ? parent::getEloquentQuery()->where('employee_id', $employee->id)
            : parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    /** @return Builder<LeaveRequest> */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $employee = auth()->user()?->employee;

        return $employee instanceof Employee
            ? parent::getRecordRouteBindingEloquentQuery()->where('employee_id', $employee->id)
            : parent::getRecordRouteBindingEloquentQuery()->whereRaw('1 = 0');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->employee !== null;
    }

    public static function form(Schema $schema): Schema
    {
        // Modal form — no Section (DESIGN.md F2).
        return $schema
            ->components([
                Select::make('leave_type_id')
                    ->label('Leave type')
                    ->relationship('leaveType', 'name')
                    ->required()
                    ->native(false)
                    ->columnSpanFull(),
                NepaliDatePicker::make('start_date')
                    ->label('From (BS)')
                    ->required(),
                NepaliDatePicker::make('end_date')
                    ->label('To (BS)')
                    ->required()
                    // Not ->afterOrEqual('start_date'): both fields still hold
                    // their raw BS string at validation time (dehydration to
                    // AD happens after validation passes), and BS month
                    // lengths vary by year, so comparing two BS strings
                    // through Carbon's Gregorian parser is not reliable —
                    // same class of bug fixed on AttendanceLogResource's date
                    // field. Convert both sides through NepaliCalendar first.
                    ->rule(static function (Get $get): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $start = $get('start_date');

                            if (blank($start)) {
                                return;
                            }

                            try {
                                $startAd = NepaliCalendar::bsToAd($start);
                                $endAd = NepaliCalendar::bsToAd($value);
                            } catch (\Throwable) {
                                return; // each field's own format rule reports this
                            }

                            if ($endAd->lt($startAd)) {
                                $fail('The end date must be on or after the start date.');
                            }
                        };
                    }),
                Textarea::make('reason')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('leaveType.name')
                    ->label('Leave type'),
                NepaliDateColumn::make('start_date')
                    ->label('From (BS)'),
                NepaliDateColumn::make('end_date')
                    ->label('To (BS)'),
                TextColumn::make('days')
                    ->suffix(fn (LeaveRequest $record): string => $record->days === 1 ? ' day' : ' days')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LeaveRequest::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => LeaveRequest::statusColor($state)),
                TextColumn::make('decision_note')
                    ->label('Note')
                    ->limit(40)
                    // No ->visible() conditional: decision_note is only ever
                    // set on reject, so the placeholder already reads "—" for
                    // pending/approved rows without needing a per-row toggle
                    // (which ->visible() on a column is not, in any case — it
                    // gates the whole column, not individual cells).
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LeaveRequest::statusOptions()),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('No leave requests yet')
            ->emptyStateDescription('Request time off and it will appear here once your manager or HR decides.')
            ->recordActions([
                Action::make('cancel')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LeaveRequest $record): bool => $record->isPending())
                    ->action(function (LeaveRequest $record): void {
                        try {
                            app(LeaveRequestService::class)->cancel($record, auth()->user());

                            Notification::make()->title('Request cancelled.')->success()->send();
                        } catch (Exception $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaveRequests::route('/'),
        ];
    }
}
