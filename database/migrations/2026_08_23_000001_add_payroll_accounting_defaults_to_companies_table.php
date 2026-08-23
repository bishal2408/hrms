<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Which chart-of-accounts row PayrollRunService posts each side of a
        // finalized payroll run to — same "configurable, not hardcoded"
        // shape as the sales-invoice accounts added in
        // add_accounting_defaults_to_companies_table.
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('salary_expense_account_id')->nullable()->after('vat_payable_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('salary_payable_account_id')->nullable()->after('salary_expense_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('statutory_payable_account_id')->nullable()->after('salary_payable_account_id')->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salary_expense_account_id');
            $table->dropConstrainedForeignId('salary_payable_account_id');
            $table->dropConstrainedForeignId('statutory_payable_account_id');
        });
    }
};
