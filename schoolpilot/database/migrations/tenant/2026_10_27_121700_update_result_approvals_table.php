<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_approvals', function (Blueprint $table) {
            // Drop the subject link, because we are now tracking the whole student result
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
            
            // Add student link
            $table->foreignId('student_id')->after('class_id')->constrained('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('result_approvals', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
        });
    }
};