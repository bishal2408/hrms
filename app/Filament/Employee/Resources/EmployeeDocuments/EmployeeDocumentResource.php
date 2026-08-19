<?php

namespace App\Filament\Employee\Resources\EmployeeDocuments;

use App\Filament\Employee\Resources\EmployeeDocuments\Pages\ManageEmployeeDocuments;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Self-service, read only: your own documents (contract, citizenship copy,
 * certificate). No create/edit/delete anywhere here — HR manages uploads
 * via the admin panel's DocumentsRelationManager; this is view + download
 * only, scoped to the signed-in user's own employee record.
 */
class EmployeeDocumentResource extends Resource
{
    protected static ?string $model = EmployeeDocument::class;

    protected static ?string $slug = 'documents';

    protected static string|UnitEnum|null $navigationGroup = 'Account';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'My Documents';

    protected static ?int $navigationSort = 10;

    /** @return Builder<EmployeeDocument> */
    public static function getEloquentQuery(): Builder
    {
        $employee = auth()->user()?->employee;

        $query = parent::getEloquentQuery()->with('documentType');

        return $employee instanceof Employee
            ? $query->where('employee_id', $employee->id)
            : $query->whereRaw('1 = 0');
    }

    /** @return Builder<EmployeeDocument> */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('documentType.name')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('original_filename')
                    ->label('File')
                    ->weight(FontWeight::Medium),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('No documents yet')
            ->emptyStateDescription('Your contract, citizenship copy and certificates appear here once HR uploads them.')
            ->recordActions([
                static::downloadAction(),
            ]);
    }

    protected static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Download')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(fn (EmployeeDocument $record): string => route('employee-documents.download', $record))
            ->openUrlInNewTab();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeDocuments::route('/'),
        ];
    }
}
