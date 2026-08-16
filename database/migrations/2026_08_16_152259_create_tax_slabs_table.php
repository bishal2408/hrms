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
        Schema::create('tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->enum('marital_status', ['single', 'married']);
            $table->decimal('lower_bound', 12, 2);
            $table->decimal('upper_bound', 12, 2)->nullable();
            $table->decimal('rate_percent', 5, 2);
            $table->date('effective_from');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['marital_status', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_slabs');
    }
};
