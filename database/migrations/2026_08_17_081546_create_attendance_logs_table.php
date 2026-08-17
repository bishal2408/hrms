<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // The calendar day this record is for — not necessarily the same
            // date as clock_in/clock_out once time-of-day is involved (a shift
            // crossing midnight still belongs to the day it started).
            $table->date('date');

            $table->dateTime('clock_in');
            $table->dateTime('clock_out')->nullable();

            // CLAUDE.md: "Design the attendance-source concept as extensible
            // (source: manual|biometric|import)." v1 only writes 'manual', but
            // the column exists now so biometric/import integrations (Phase 6)
            // need no schema change, only a new value.
            $table->enum('source', ['manual', 'biometric', 'import'])->default('manual');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One row per employee per day.
            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
