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
        // How PayrollCalculationService derives basicAfterAttendance for
        // every run — a closed, structural two-way choice (same reasoning as
        // accounts.account_type: never admin-extended, so a plain column
        // with class-constant values — see Company::PAYROLL_MODE_* — not a
        // lookup table). Literal default value, not the class constant: a
        // migration is a frozen historical artifact and shouldn't depend on
        // application code that might change (same convention
        // create_accounts_table already follows for account_type's values).
        // Defaults to the existing attendance-prorated behaviour so no
        // existing company's payroll changes shape without an explicit
        // opt-in.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('payroll_salary_calculation_mode')
                ->default('attendance_prorated')
                ->after('statutory_payable_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('payroll_salary_calculation_mode');
        });
    }
};
