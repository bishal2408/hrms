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
        Schema::create('payroll_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['provident_fund', 'social_security_fund']);
            $table->decimal('employee_contribution_percent', 5, 2);
            $table->decimal('employer_contribution_percent', 5, 2);
            $table->date('effective_from');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_rates');
    }
};
