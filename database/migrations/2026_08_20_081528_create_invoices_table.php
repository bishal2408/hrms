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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // Gap-free per Nepali fiscal year (CLAUDE.md: VAT periods key
            // off the fiscal year, and real invoice series conventionally
            // reset each one). fiscal_year + sequence are the numbers
            // InvoiceNumberGenerator actually reasons over (MAX(sequence)
            // per year); invoice_number is just their formatted display
            // form, stored so it never needs re-deriving.
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('sequence');
            $table->string('invoice_number')->unique();

            $table->date('issue_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Cancel-don't-delete (CLAUDE.md): no hard deletes on posted
            // financial records. An issued invoice is immutable; a mistake
            // is corrected by cancelling (reverses the GL posting via
            // JournalEntryService::reverse()) and, if needed, issuing a
            // fresh invoice — never editing this row after issue_date.
            $table->enum('status', ['issued', 'cancelled'])->default('issued');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // The GL posting isn't a column here — it's found via
            // journal_entries.reference_type='invoice' + reference_id=this
            // row's id, the polymorphic-tag columns already added in Phase
            // 4a specifically for this.
            $table->unique(['fiscal_year', 'sequence']);
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
