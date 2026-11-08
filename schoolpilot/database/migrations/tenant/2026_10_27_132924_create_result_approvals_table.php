<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Earlier migrations create this table and then add student_id.
        // Keep this migration safe for fresh and already-provisioned tenants.
        if (! Schema::hasTable('result_approvals') || Schema::hasColumn('result_approvals', 'class_teacher_approved_by')) {
            return;
        }

        Schema::table('result_approvals', function (Blueprint $table) {
            $table->foreignId('class_teacher_approved_by')
                ->nullable()
                ->after('submitted_by')
                ->constrained('staff')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('result_approvals') || ! Schema::hasColumn('result_approvals', 'class_teacher_approved_by')) {
            return;
        }

        Schema::table('result_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_teacher_approved_by');
        });
    }
};
