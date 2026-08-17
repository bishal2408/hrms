<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_type_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            // One amount per component per structure version — two "Transport
            // Allowance" lines on the same version would be an entry mistake,
            // not a valid pay structure. Named explicitly: the default
            // generated name exceeds MySQL's 64-char identifier limit.
            $table->unique(['salary_structure_id', 'salary_component_type_id'], 'salary_structure_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structure_items');
    }
};
