<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'finalized', 'cancelled'])->default('draft');

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('calculated_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();

            // Employees skipped during calculation (missing salary structure,
            // missing PF/SSF rate, missing tax slab) — [{employee_id, name,
            // reason}]. A run can still proceed with partial success; HR sees
            // exactly who was skipped and why instead of a run that silently
            // paid nobody because one employee's config was missing.
            $table->json('skipped_employees')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One run per exact period — running the same month twice is
            // almost always a mistake, not an intentional second run.
            $table->unique(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
