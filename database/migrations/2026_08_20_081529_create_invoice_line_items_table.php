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
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);

            // Some goods/services are VAT-exempt under Nepali law even for
            // a VAT-registered business — per-line, not a single flag on
            // the whole invoice.
            $table->boolean('is_vatable')->default(true);

            // quantity * unit_price, frozen at issue time — a snapshot like
            // every other posted-financial-record line in this app
            // (Payslip's allowance/deduction items), not recomputed later.
            $table->decimal('amount', 12, 2);

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
