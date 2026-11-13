<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->enum('type', ['allowance', 'deduction']);
            $table->enum('calculation', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('default_value', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_salary_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->decimal('base_salary', 14, 2);
            $table->char('currency', 3)->default('NGN');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['staff_id', 'is_active', 'effective_from'], 'salary_assignment_staff_active');
        });

        Schema::create('salary_assignment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_assignment_id')->constrained('staff_salary_assignments')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->restrictOnDelete();
            $table->decimal('amount_override', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['salary_assignment_id', 'salary_component_id'], 'salary_assignment_component_unique');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->char('currency', 3)->default('NGN');
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->unsignedInteger('staff_count')->default(0);
            $table->decimal('gross_total', 16, 2)->default(0);
            $table->decimal('deductions_total', 16, 2)->default(0);
            $table->decimal('net_total', 16, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 150)->nullable();
            $table->timestamps();
            $table->unique(['year', 'month', 'currency'], 'payroll_period_unique');
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignId('salary_assignment_id')->nullable()->constrained('staff_salary_assignments')->nullOnDelete();
            $table->string('staff_name');
            $table->string('staff_no');
            $table->decimal('base_salary', 14, 2);
            $table->decimal('allowances_total', 14, 2)->default(0);
            $table->decimal('deductions_total', 14, 2)->default(0);
            $table->decimal('gross_pay', 14, 2);
            $table->decimal('net_pay', 14, 2);
            $table->json('breakdown');
            $table->timestamps();
            $table->unique(['payroll_period_id', 'staff_id'], 'payroll_period_staff_unique');
            $table->index(['staff_id', 'payroll_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('salary_assignment_components');
        Schema::dropIfExists('staff_salary_assignments');
        Schema::dropIfExists('salary_components');
    }
};
