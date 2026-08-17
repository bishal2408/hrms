<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Self-service login. Nullable: staff who never sign in (drivers,
            // cleaners) still need an employee record, and a login can be
            // attached later.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('employee_code')->unique();

            // Personal
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            // Not optional: the TDS slab table is selected by marital status,
            // so payroll cannot run without it (see tax_slabs.marital_status).
            $table->enum('marital_status', ['single', 'married']);
            $table->string('personal_email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            // Statutory identity
            $table->string('pan_number')->nullable();
            $table->string('citizenship_number')->nullable();

            // Employment
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            // Reporting line. Phase 2 approvals and manager-scoped access both
            // key off this.
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('hired_at');
            $table->date('terminated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('hired_at');
            $table->index('terminated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
