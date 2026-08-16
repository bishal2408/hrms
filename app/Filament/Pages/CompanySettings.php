<?php

namespace App\Filament\Pages;

use App\Models\Company;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CompanySettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.company-settings';

    protected static ?string $slug = 'setup/company';

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Company';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'hr_admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(Company::current()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->description('Appears on payslips, invoices and every compliance document this system produces.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Registered name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('pan_number')
                            ->label('PAN')
                            ->maxLength(255)
                            ->helperText('Permanent Account Number issued by the IRD.'),
                        TextInput::make('vat_number')
                            ->label('VAT number')
                            ->maxLength(255)
                            ->helperText('Leave empty if the company is not VAT registered.'),
                    ]),
                Section::make('Contact')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Company::current()->fill($this->form->getState())->save();

        Notification::make()
            ->title('Company settings saved')
            ->success()
            ->send();
    }
}
