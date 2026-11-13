<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parent_notifications')) {
            return;
        }

        Schema::create('parent_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 180);
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['attendance_record_id', 'user_id']);
            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_notifications');
    }
};
