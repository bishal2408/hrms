<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();

            // Signed: positive adds to net pay, negative claws back. This is
            // the only way a finalized payslip's effective pay ever changes —
            // CLAUDE.md: "create a reversal/adjustment record — don't mutate a
            // paid payslip." Correcting an adjustment means adding another
            // one, not editing this row.
            $table->decimal('amount', 12, 2);
            $table->text('reason');

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_adjustments');
    }
};
