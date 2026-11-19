<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollment_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('to_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('from_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('to_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('change_type');
            $table->text('reason');
            $table->date('effective_on');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'effective_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollment_history');
    }
};
