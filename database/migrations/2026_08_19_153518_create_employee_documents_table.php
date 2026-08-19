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
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();

            // Path on the private 'local' disk (storage/app/private) — never
            // the public disk. Filament stores the upload under a randomised
            // filename (safe against PHP-execution risk on a preserved
            // extension); the human-readable name is kept separately so
            // downloads can offer it back to the user.
            $table->string('path');
            $table->string('original_filename');

            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Deliberately no soft deletes: a document row has no unique
            // constraint to collide with, and EmployeeDocument::booted()
            // deletes the underlying file when the row is deleted, so a
            // "discard" here is meant to be genuinely final, not hidden —
            // see the remove_soft_deletes_from_payroll_runs_table migration
            // for what happens when those two ideas get combined.
            $table->index(['employee_id', 'document_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
