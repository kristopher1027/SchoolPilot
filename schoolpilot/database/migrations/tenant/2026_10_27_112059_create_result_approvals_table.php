<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            
            $table->string('status')->default('draft');
            
            $table->foreignId('submitted_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('hod_approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('dean_approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('staff')->nullOnDelete();
            
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_approvals');
    }
};