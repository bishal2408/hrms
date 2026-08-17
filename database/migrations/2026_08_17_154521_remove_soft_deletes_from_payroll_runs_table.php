<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PayrollRun was soft-deleting, but its unique index on
 * (period_start, period_end) has no idea what `deleted_at` means — it is a
 * plain column-value constraint, blind to Eloquent's soft-delete scope. The
 * mismatch: PayrollRunService::discard() only ever runs on a draft that
 * "never paid anyone, so there is nothing to preserve" (its own docblock),
 * so soft-deleting it bought nothing — but it left the row (and, since a
 * soft-delete UPDATE never fires an FK cascade the way a real DELETE does,
 * its child payslips too) sitting in the table forever, silently blocking
 * that period from ever being run again with a raw SQL 1062 duplicate-key
 * error instead of a clean message.
 *
 * A discarded draft should be genuinely gone. Hard-delete every row that was
 * already soft-deleted (their orphaned payslips cascade-delete for free,
 * since this is a real DELETE), then drop the column so
 * PayrollRunService::discard()'s existing $run->delete() call becomes a real
 * delete going forward — no service code change needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_runs')->whereNotNull('deleted_at')->delete();

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
