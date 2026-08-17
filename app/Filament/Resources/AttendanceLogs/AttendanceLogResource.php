<?php

namespace App\Filament\Resources\AttendanceLogs;

use App\Filament\Forms\Components\NepaliDatePicker;
use App\Filament\Resources\AttendanceLogs\Pages\ManageAttendanceLogs;
use App\Filament\Tables\Columns\NepaliDateColumn;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\NepaliCalendar;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Admin view of attendance: the full log across every employee, for
 * correcting a forgotten punch or reviewing a day.
 *
 * Self-service clock-in/clock-out is a different surface entirely — the
 * employee panel's own page, backed by AttendanceService's state machine.
 * This resource edits rows directly, since a correction is not a state
 * transition.
 */
class AttendanceLogResource extends Resource
{
    protected static ?string $model = AttendanceLog::class;

    protected static ?string $slug = 'attendance/logs';

    protected static string|UnitEnum|null $navigationGroup = 'Attendance & Leave';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Attendance';

    public static function form(Schema $schema): Schema
    {
        // Modal form — no Section (DESIGN.md F2).
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'first_name', fn (Builder $query) => $query->active())
                    ->getOptionLabelFromRecordUsing(fn (Employee $record): string => $record->full_name)
                    ->searchable(['first_name', 'last_name', 'employee_code'])
                    ->preload()
                    ->required()
                    ->columnSpanFull(),
                NepaliDatePicker::make('date')
                    ->label('Date (BS)')
                    ->required()
                    // Not a plain ->unique(): the field's own state is still
                    // the BS string the user typed at validation time (the
                    // component only converts to AD on dehydrate), so a
                    // straight column-uniqueness check against the AD 'date'
                    // column would never correctly match. Convert first.
                    ->rule(static function (Get $get, ?Model $record): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            try {
                                $ad = NepaliCalendar::bsToAd($value)->toDateString();
                            } catch (\Throwable) {
                                return; // the field's own format rule reports this
                            }

                            $duplicate = AttendanceLog::query()
                                ->where('employee_id', $get('employee_id'))
                                ->whereDate('date', $ad)
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($duplicate) {
                                $fail('This employee already has a record for that date.');
                            }
                        };
                    })
                    ->helperText('The calendar day this record belongs to.'),
                Select::make('source')
                    ->options(AttendanceLog::sourceOptions())
                    ->required()
                    ->native(false)
                    ->default(AttendanceLog::SOURCE_MANUAL),
                DateTimePicker::make('clock_in')
                    ->required()
                    ->seconds(false),
                DateTimePicker::make('clock_out')
                    ->seconds(false)
                    ->after('clock_in')
                    ->helperText('Leave empty if the employee has not clocked out yet.'),
                Textarea::make('notes')
                    ->helperText('Why this record was added or changed by hand.')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->weight(FontWeight::Medium)
                    ->description(fn (AttendanceLog $record): ?string => $record->employee?->employee_code)
                    ->searchable(['employee.first_name', 'employee.last_name'])
                    ->sortable(),
                NepaliDateColumn::make('date')
                    ->label('Date (BS)')
                    ->sortable(),
                TextColumn::make('clock_in')
                    ->label('Clock in')
                    ->time('h:i A'),
                TextColumn::make('clock_out')
                    ->label('Clock out')
                    ->time('h:i A')
                    ->placeholder('Still clocked in'),
                TextColumn::make('worked_minutes')
                    ->label('Worked')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : sprintf('%dh %02dm', intdiv($state, 60), $state % 60))
                    ->alignEnd(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceLog::sourceOptions()[$state] ?? $state),
            ])
            ->filters([
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload(),
                Filter::make('date')
                    ->schema([
                        NepaliDatePicker::make('from')->label('From (BS)'),
                        NepaliDatePicker::make('until')->label('Until (BS)'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('date', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('date', '<=', $until))),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedClock)
            ->emptyStateHeading('No attendance records yet')
            ->emptyStateDescription('Records appear here once employees start clocking in, or you can add one to correct a forgotten punch.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendanceLogs::route('/'),
        ];
    }
}
