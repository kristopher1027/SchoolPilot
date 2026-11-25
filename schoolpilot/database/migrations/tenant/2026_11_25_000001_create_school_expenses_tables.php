<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('expense_categories')->insert([
            ['name' => 'Utilities', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Supplies', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Maintenance', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Transport', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Staff Welfare', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Security', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('school_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('description', 180);
            $table->string('vendor', 160)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30);
            $table->string('reference', 100)->nullable()->unique();
            $table->date('expense_date');
            $table->enum('status', ['paid', 'unpaid'])->default('paid');
            $table->date('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['expense_date', 'category_id']);
            $table->index(['status', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_expenses');
        Schema::dropIfExists('expense_categories');
    }
};
