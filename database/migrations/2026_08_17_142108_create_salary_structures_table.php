<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 12, 2);

            // Effective-dated like PayrollRate/TaxSlab (CLAUDE.md: rates are
            // data with an effective-date so historical payroll runs stay
            // reproducible). A new row supersedes the old one — pay changes
            // are a new version, never an edit to an existing one.
            $table->date('effective_from');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};
