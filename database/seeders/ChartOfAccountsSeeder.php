<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

/**
 * A minimal starter chart of accounts, enough to post real entries against
 * (cash/bank movements, receivables, VAT, sales, a general expense bucket)
 * without inventing figures — payroll_accountant adds more from Setup as
 * the business actually needs them.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Cash', 'account_type' => Account::TYPE_ASSET],
            ['code' => '1010', 'name' => 'Bank', 'account_type' => Account::TYPE_ASSET],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'account_type' => Account::TYPE_ASSET],
            ['code' => '2000', 'name' => 'Accounts Payable', 'account_type' => Account::TYPE_LIABILITY],
            ['code' => '2100', 'name' => 'VAT Payable', 'account_type' => Account::TYPE_LIABILITY],
            ['code' => '2200', 'name' => 'Salary Payable', 'account_type' => Account::TYPE_LIABILITY],
            ['code' => '2300', 'name' => 'Statutory Payable (PF/SSF/TDS)', 'account_type' => Account::TYPE_LIABILITY],
            ['code' => '3000', 'name' => "Owner's Equity", 'account_type' => Account::TYPE_EQUITY],
            ['code' => '3900', 'name' => 'Retained Earnings', 'account_type' => Account::TYPE_EQUITY],
            ['code' => '4000', 'name' => 'Sales Revenue', 'account_type' => Account::TYPE_REVENUE],
            ['code' => '5000', 'name' => 'General Expense', 'account_type' => Account::TYPE_EXPENSE],
            ['code' => '5100', 'name' => 'Salary Expense', 'account_type' => Account::TYPE_EXPENSE],
        ];

        foreach ($accounts as $account) {
            Account::create($account + ['is_active' => true]);
        }
    }
}
