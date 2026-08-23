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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->text('description');

            // What produced this entry — null for a manually-posted entry,
            // 'invoice' (etc.) once Phase 4b posts automatically. Same
            // polymorphic-tag shape as employee_documents/stock_movements-
            // style patterns elsewhere: a string tag, not an Eloquent
            // morph relation, since nothing here needs to load the source
            // model back through it yet.
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // A posted entry is immutable — no edit or delete action exists
            // anywhere (CLAUDE.md: no hard deletes on posted financial
            // records). A mistake is corrected by posting a reversal
            // (JournalEntryService::reverse()), never by touching the
            // original — same "never mutate, always add a correcting
            // record" shape as PayslipAdjustment in the payroll domain.
            $table->foreignId('reverses_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('entry_date');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
