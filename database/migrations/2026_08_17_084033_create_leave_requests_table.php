<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            // Inclusive day count, derived from start/end and kept in sync by
            // the model whenever either date changes (LeaveRequest::booted()).
            // Stored so balance totals can be a single SQL SUM instead of
            // pulling every row into PHP to re-derive it — a per-request
            // arithmetic cache, not the reconciled "leave_balances" ledger
            // that was explicitly deferred (see docs/ROADMAP.md 2b).
            $table->unsignedSmallInteger('days');

            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['leave_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
