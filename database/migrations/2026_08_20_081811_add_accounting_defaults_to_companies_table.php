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
        // Which chart-of-accounts row InvoiceService posts each side of a
        // sales invoice to. Not looked up by account code string (an admin
        // renaming/recoding an account would silently break invoicing) —
        // an explicit, nullable choice here, same "configurable, not
        // hardcoded" principle as PayrollRate/TaxSlab/VatRate.
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('accounts_receivable_account_id')->nullable()->after('email')->constrained('accounts')->nullOnDelete();
            $table->foreignId('sales_revenue_account_id')->nullable()->after('accounts_receivable_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('vat_payable_account_id')->nullable()->after('sales_revenue_account_id')->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounts_receivable_account_id');
            $table->dropConstrainedForeignId('sales_revenue_account_id');
            $table->dropConstrainedForeignId('vat_payable_account_id');
        });
    }
};
