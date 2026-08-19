<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Services\EmployeeDocumentService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sensitive PII (citizenship copies, contracts) — the tab itself only shows
 * up for hr_admin/payroll_accountant, since Filament auto-checks
 * `ViewAny:EmployeeDocument` before rendering a relation manager
 * (RelationManager::canViewForRecord()), and RolePermissionSeeder never
 * grants that permission to 'manager'. Files live on the private 'local'
 * disk and are only ever reachable through the authorized download route —
 * never a public URL.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['documentType', 'uploadedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('documentType.name')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('original_filename')
                    ->label('File')
                    ->weight(FontWeight::Medium)
                    ->description(fn (EmployeeDocument $record): ?string => $record->notes)
                    ->searchable(),
                TextColumn::make('uploadedBy.name')
                    ->label('Uploaded by')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('document_type_id')
                    ->label('Type')
                    ->relationship('documentType', 'name'),
            ])
            ->persistFiltersInSession()
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('No documents yet')
            ->emptyStateDescription('Upload a contract, citizenship copy or certificate for this employee.')
            ->headerActions([
                $this->uploadAction(),
            ])
            ->recordActions([
                $this->downloadAction(),
                DeleteAction::make(),
            ]);
    }

    protected function uploadAction(): Action
    {
        return Action::make('upload')
            ->label('Upload document')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (): bool => auth()->user()?->can('Create:EmployeeDocument') ?? false)
            ->schema([
                Select::make('document_type_id')
                    ->label('Document type')
                    ->options(fn () => DocumentType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->native(false),
                FileUpload::make('path')
                    ->label('File')
                    ->disk('local')
                    ->visibility('private')
                    ->directory(fn (): string => 'employee-documents/'.$this->getOwnerRecord()->getKey())
                    ->storeFileNamesIn('original_filename')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10240)
                    ->required(),
                Textarea::make('notes')
                    ->maxLength(1000),
            ])
            ->action(function (array $data): void {
                app(EmployeeDocumentService::class)->upload(
                    $this->getOwnerRecord(),
                    DocumentType::findOrFail($data['document_type_id']),
                    $data['path'],
                    $data['original_filename'],
                    $data['notes'] ?? null,
                    auth()->user(),
                );

                Notification::make()->title('Document uploaded.')->success()->send();
            });
    }

    protected function downloadAction(): Action
    {
        return Action::make('download')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(fn (EmployeeDocument $record): string => route('employee-documents.download', $record))
            ->openUrlInNewTab();
    }
}
