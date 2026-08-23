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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');

            // A raw enum, not a lookup table: asset/liability/equity/revenue/
            // expense is a closed, structural set from double-entry
            // bookkeeping itself — it will never be admin-extended, the same
            // reasoning that already put LeaveRequest.status on a plain
            // enum column rather than a lookup table in this codebase.
            $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense']);

            $table->foreignId('parent_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('account_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
