<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Academic Structure
        Schema::create('academic_sessions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('is_current')->default(false);
            $t->timestamps();
        });

        Schema::create('terms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('is_current')->default(false);
            $t->timestamps();
        });

        Schema::create('sections', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique(); // NURSERY, PRIMARY, SECONDARY
            $t->timestamps();
        });

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('classes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('section_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->integer('level');
            $t->foreignId('next_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->nullable();
            $t->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('class_subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['class_id', 'subject_id']);
        });

        // 2. People & Roles
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('phone')->nullable();
            $t->string('password');
            $t->enum('role', [
                'school_admin', 'principal', 'vice_principal', 'dean', 'hod',
                'class_teacher', 'subject_teacher', 'bursar', 'student', 'parent',
            ]);
            $t->boolean('is_active')->default(true);
            $t->string('photo_url')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('parents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('occupation')->nullable();
            $t->text('address')->nullable();
            $t->string('relationship')->nullable();
            $t->timestamps();
        });

        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->string('admission_no')->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('first_name');
            $t->string('last_name');
            $t->string('middle_name')->nullable();
            $t->enum('gender', ['male', 'female']);
            $t->date('date_of_birth');
            $t->string('photo_url')->nullable();
            $t->foreignId('section_id')->constrained();
            $t->foreignId('class_id')->nullable()->constrained()->nullOnDelete();
            $t->date('date_admitted');
            $t->enum('status', ['active', 'graduated', 'transferred', 'withdrawn'])->default('active');
            $t->text('address')->nullable();
            $t->string('blood_group')->nullable();
            $t->string('genotype')->nullable();
            $t->text('allergies')->nullable();
            $t->timestamps();
        });

        Schema::create('student_parents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parent_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
            $t->unique(['student_id', 'parent_id']);
        });

        Schema::create('staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('staff_no')->unique();
            $t->string('designation')->nullable();
            $t->date('date_hired')->nullable();
            $t->string('qualification')->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
        });

        Schema::create('staff_departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_hod')->default(false);
            $t->timestamps();
            $t->unique(['staff_id', 'department_id']);
        });

        Schema::create('teacher_subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });

        Schema::create('class_teachers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['class_id', 'academic_session_id']);
        });

        // 3. Assessment Engine
        Schema::create('assessment_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('section_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->enum('type', ['ca_test', 'ca_assignment', 'ca_project', 'ca_practical', 'ca_presentation', 'ca_quiz', 'exam', 'observation', 'competency']);
            $t->decimal('max_score', 5, 2);
            $t->decimal('weight', 5, 2);
            $t->integer('display_order');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('grading_scales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('section_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::create('grading_bands', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grading_scale_id')->constrained()->cascadeOnDelete();
            $t->decimal('min_score', 5, 2);
            $t->decimal('max_score', 5, 2);
            $t->string('grade');
            $t->string('remark')->nullable();
            $t->text('descriptor')->nullable();
            $t->integer('points')->nullable();
            $t->timestamps();
        });

        Schema::create('skills_rubrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('section_id')->constrained()->cascadeOnDelete();
            $t->string('skill_name');
            $t->text('description')->nullable();
            $t->integer('scale_min')->default(1);
            $t->integer('scale_max')->default(5);
            $t->integer('display_order')->default(0);
            $t->timestamps();
        });

        Schema::create('observation_ratings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('result_sheet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assessment_component_id')->constrained()->cascadeOnDelete();
            $t->integer('rating');
            $t->text('comment')->nullable();
            $t->timestamps();
        });

        Schema::create('score_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assessment_component_id')->constrained()->cascadeOnDelete();
            $t->decimal('score', 6, 2);
            $t->foreignId('entered_by')->constrained('staff')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['student_id', 'subject_id', 'term_id', 'assessment_component_id'], 'score_unique');
        });

        // 4. Results & Workflow
        Schema::create('result_sheets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->decimal('total_score', 8, 2)->nullable();
            $t->decimal('average', 8, 2)->nullable();
            $t->integer('position')->nullable();
            $t->json('grade_summary')->nullable();
            $t->json('competency_summary')->nullable();
            $t->integer('attendance_present')->nullable();
            $t->integer('attendance_total')->nullable();
            $t->text('class_teacher_comment')->nullable();
            $t->text('next_steps')->nullable();
            $t->text('hod_comment')->nullable();
            $t->text('dean_comment')->nullable();
            $t->text('principal_comment')->nullable();
            $t->enum('status', ['draft', 'submitted', 'hod_approved', 'dean_approved', 'approved', 'published', 'returned'])->default('draft');
            $t->foreignId('hod_approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamp('hod_approved_at')->nullable();
            $t->foreignId('dean_approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamp('dean_approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->foreignId('returned_by')->nullable()->constrained('staff')->nullOnDelete();
            $t->text('return_reason')->nullable();
            $t->timestamp('returned_at')->nullable();
            $t->string('verify_token', 64)->nullable()->unique();
            $t->timestamps();
            $t->unique(['student_id', 'term_id']);
        });

        Schema::create('result_sheet_subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('result_sheet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->decimal('ca_total', 6, 2)->nullable();
            $t->decimal('exam_score', 6, 2)->nullable();
            $t->decimal('total', 6, 2)->nullable();
            $t->string('grade')->nullable();
            $t->integer('position')->nullable();
            $t->text('remark')->nullable();
            $t->timestamps();
        });

        Schema::create('subject_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('result_sheet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('hod_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->enum('status', ['pending', 'approved', 'returned'])->default('pending');
            $t->text('comment')->nullable();
            $t->timestamp('acted_at')->nullable();
            $t->timestamps();
            $t->unique(['result_sheet_id', 'subject_id']);
        });

        Schema::create('skill_ratings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('result_sheet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('skill_rubric_id')->constrained()->cascadeOnDelete();
            $t->integer('rating');
            $t->text('teacher_comment')->nullable();
            $t->timestamps();
            $t->unique(['result_sheet_id', 'skill_rubric_id']);
        });

        // 5. Supporting Tables (Curriculum, Attendance, Fees, System)
        Schema::create('curriculum_guides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->integer('week_no');
            $t->string('topic');
            $t->json('learning_outcomes')->nullable();
            $t->json('resources')->nullable();
            $t->string('assessment_method')->nullable();
            $t->string('file_url')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('staff')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->enum('status', ['present', 'absent', 'late', 'excused']);
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->string('picked_up_by')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('staff')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'date']);
        });

        Schema::create('fee_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('class_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->decimal('total_amount', 12, 2);
            $t->decimal('amount_paid', 12, 2)->default(0);
            $t->decimal('balance', 12, 2);
            $t->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $t->date('due_date')->nullable();
            $t->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('fee_item_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->enum('method', ['cash', 'transfer', 'paystack', 'flutterwave']);
            $t->string('reference')->nullable();
            $t->timestamp('paid_at');
            $t->foreignId('recorded_by')->nullable()->constrained('staff')->nullOnDelete();
            $t->string('receipt_no')->unique();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('old_value')->nullable();
            $t->json('new_value')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();
        });

                Schema::create('notifications', function (Blueprint $t) {
            $t->string('id')->primary(); // UUID (changed this line)
            $t->string('type');
            $t->string('notifiable_type');
            $t->unsignedBigInteger('notifiable_id');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // Drop in reverse order of creation to respect foreign key constraints
        $tables = [
            'settings', 'notifications', 'audit_logs', 'payments', 'invoice_items', 'invoices', 'fee_items',
            'attendance_records', 'curriculum_guides', 'skill_ratings', 'subject_approvals',
            'result_sheet_subjects', 'result_sheets', 'score_entries', 'observation_ratings',
            'skills_rubrics', 'grading_bands', 'grading_scales', 'assessment_components',
            'class_teachers', 'teacher_subjects', 'staff_departments', 'staff',
            'student_parents', 'students', 'parents', 'users',
            'class_subjects', 'subjects', 'classes', 'departments', 'sections', 'terms', 'academic_sessions'
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};