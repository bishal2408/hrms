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
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();

            // Effective-dated, like PayrollRate/TaxSlab (CLAUDE.md: rates
            // are policy-set data, not a hardcoded constant, so historical
            // invoices stay reproducible even after the rate changes). A
            // new rate is a new row, never an edit to an existing one.
            $table->decimal('rate_percent', 5, 2);
            $table->date('effective_from');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('effective_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_rates');
    }
};
