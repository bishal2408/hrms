<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            // A frozen copy of PayrollCalculationResult — every field below is
            // written once, at calculation time, and never recomputed. Once
            // the run is finalized these rows are immutable (enforced by
            // PayrollRunService, not a DB constraint); a correction is a
            // PayslipAdjustment, never an update to a value here.
            $table->decimal('basic_salary', 12, 2);
            $table->unsignedSmallInteger('total_days');
            $table->unsignedSmallInteger('unpaid_days');
            $table->decimal('basic_after_attendance', 12, 2);

            // Itemized lines as a snapshot, not a relation to
            // salary_component_types: if a component is later renamed or
            // deleted, an already-issued payslip must still show exactly what
            // it showed when it was generated. [{name, amount}].
            $table->json('allowance_items')->nullable();
            $table->json('deduction_items')->nullable();

            $table->decimal('allowances_total', 12, 2);
            $table->decimal('deductions_total', 12, 2);
            $table->decimal('gross_pay', 12, 2);
            $table->decimal('pf_employee', 12, 2);
            $table->decimal('pf_employer', 12, 2);
            $table->decimal('ssf_employee', 12, 2);
            $table->decimal('ssf_employer', 12, 2);
            $table->decimal('taxable_income', 12, 2);
            $table->decimal('tds', 12, 2);
            $table->decimal('net_pay', 12, 2);

            $table->timestamps();

            // One payslip per employee per run.
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
