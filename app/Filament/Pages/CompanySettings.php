<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\Company;
use BackedEnum;
use Filament\Forms\Components\Select;
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
        $data = Company::current()->toArray();
        // Before the company's first-ever save, Company::current() is an
        // unsaved firstOrNew() instance with no key at all for this column
        // (not even null) — the Select's own default() isn't reliably
        // applied through a fill() that already supplies other keys, so it's
        // set explicitly here rather than left to depend on that.
        $data['payroll_salary_calculation_mode'] ??= Company::PAYROLL_MODE_ATTENDANCE_PRORATED;

        $this->form->fill($data);
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
                Section::make('Accounting defaults')
                    ->description('Where InvoiceService posts each side of a sales invoice. Required before any invoice can be issued.')
                    ->columns(3)
                    ->schema([
                        // Plain ->options(), not ->relationship(): this page
                        // fills/saves the form manually (Company::current()
                        // ->fill($this->form->getState())->save() below),
                        // it isn't bound to a Filament record the way an
                        // EditRecord page is, and relationship() depends on
                        // exactly that binding to load/save correctly.
                        Select::make('accounts_receivable_account_id')
                            ->label('Accounts Receivable')
                            ->options(fn () => static::accountOptions())
                            ->searchable()
                            ->native(false),
                        Select::make('sales_revenue_account_id')
                            ->label('Sales Revenue')
                            ->options(fn () => static::accountOptions())
                            ->searchable()
                            ->native(false),
                        Select::make('vat_payable_account_id')
                            ->label('VAT Payable')
                            ->options(fn () => static::accountOptions())
                            ->searchable()
                            ->native(false)
                            ->helperText('Only needed once a VAT rate is configured and an invoice has a VAT-applicable line.'),
                    ]),
                Section::make('Payroll calculation')
                    ->description('How PayrollCalculationService derives basic salary for every run.')
                    ->schema([
                        Select::make('payroll_salary_calculation_mode')
                            ->label('Basic salary mode')
                            ->options(Company::payrollSalaryCalculationModeOptions())
                            // Not required, matching the accounting Selects
                            // above: null already has a well-defined, safe
                            // meaning here (PayrollCalculationService treats
                            // it the same as the default mode), unlike an
                            // unset accounting account which fails loudly at
                            // point of use. The pre-first-save default is set
                            // explicitly in mount(), not relied on here.
                            ->native(false)
                            ->helperText('Pro-rated: basic salary scales with attendance/paid-leave days, unattended days are unpaid. Full: the complete basic salary is paid regardless of attendance — only an explicit unpaid-leave day reduces it. Both still prorate for a mid-period hire or termination.'),
                    ]),
                Section::make('Payroll accounting defaults')
                    ->description('Where PayrollRunService posts each side of a finalized payroll run. Required before any run can be finalized.')
                    ->columns(3)
                    ->schema([
                        Select::make('salary_expense_account_id')
                            ->label('Salary Expense')
                            ->options(fn () => static::accountOptions())
                            ->searchable()
                            ->native(false),
                        Select::make('salary_payable_account_id')
                            ->label('Salary Payable')
                            ->options(fn () => static::accountOptions())
                            ->searchable()
                            ->native(false),
                        Select::make('statutory_payable_account_id')
                            ->label('Statutory Payable')
                            ->options(fn () => static::accountOptions())
                            ->searchable()
                            ->native(false)
                            ->helperText('PF, SSF and TDS withheld/contributed. Only needed once a run has statutory amounts to post.'),
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

    /** @return array<int, string> */
    private static function accountOptions(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} — {$account->name}"])
            ->all();
    }
}
