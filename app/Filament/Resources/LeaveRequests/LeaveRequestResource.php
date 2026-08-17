<?php

namespace App\Filament\Resources\LeaveRequests;

use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveRequestService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * HR/manager view: decide requests, don't file them.
 *
 * Every mutation goes through LeaveRequestService, never straight to the
 * model — the balance check, overlap check and who-may-decide rule live
 * there exactly once, shared with the employee panel's self-service
 * resource. This resource never registers an EditAction either: a decided
 * request is not something you edit, only reverse via a fresh request.
 */
class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $slug = 'attendance/leave-requests';

    protected static string|UnitEnum|null $navigationGroup = 'Attendance & Leave';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Leave Requests';

    /** @return Builder<LeaveRequest> */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee', 'leaveType']);

        $user = auth()->user();

        return $user instanceof User ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        // Read-only view of a decided/pending request — see the class
        // docblock for why there is no edit form here.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->weight(FontWeight::Medium)
                    ->description(fn (LeaveRequest $record): ?string => $record->employee?->employee_code)
                    ->searchable(['employee.first_name', 'employee.last_name'])
                    ->sortable(),
                TextColumn::make('leaveType.name')
                    ->label('Leave type'),
                NepaliDateColumn::make('start_date')
                    ->label('From (BS)')
                    ->sortable(),
                NepaliDateColumn::make('end_date')
                    ->label('To (BS)')
                    ->sortable(),
                TextColumn::make('days')
                    ->suffix(fn (LeaveRequest $record): string => $record->days === 1 ? ' day' : ' days')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LeaveRequest::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => LeaveRequest::statusColor($state)),
                TextColumn::make('decidedBy.name')
                    ->label('Decided by')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LeaveRequest::statusOptions()),
                SelectFilter::make('leave_type_id')
                    ->label('Leave type')
                    ->relationship('leaveType', 'name'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('No leave requests yet')
            ->emptyStateDescription('Requests submitted by employees appear here for approval.')
            ->recordActions([
                static::approveAction(),
                static::rejectAction(),
            ]);
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn (LeaveRequest $record): string => "Approve {$record->days} day(s) of {$record->leaveType?->name} for {$record->employee?->full_name}?")
            ->visible(fn (LeaveRequest $record): bool => static::canDecide($record))
            ->action(function (LeaveRequest $record): void {
                try {
                    app(LeaveRequestService::class)->approve($record, auth()->user());

                    Notification::make()->title('Leave request approved.')->success()->send();
                } catch (Exception $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->helperText('Shown to the employee.'),
            ])
            ->visible(fn (LeaveRequest $record): bool => static::canDecide($record))
            ->action(function (LeaveRequest $record, array $data): void {
                try {
                    app(LeaveRequestService::class)->reject($record, auth()->user(), $data['reason']);

                    Notification::make()->title('Leave request rejected.')->success()->send();
                } catch (Exception $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * Gates the buttons themselves — the service enforces the same rule
     * again when the action actually runs (defense in depth, not the only
     * check).
     */
    protected static function canDecide(LeaveRequest $record): bool
    {
        $user = auth()->user();

        if (! $record->isPending() || ! $user instanceof User) {
            return false;
        }

        return app(LeaveRequestService::class)->canDecide($record, $user);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaveRequests::route('/'),
        ];
    }
}
