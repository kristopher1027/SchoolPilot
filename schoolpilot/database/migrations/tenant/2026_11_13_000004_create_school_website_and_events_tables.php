<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_website_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name', 180)->nullable();
            $table->string('tagline', 220)->nullable();
            $table->text('about')->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->string('cover_image_url', 2048)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('primary_colour', 20)->default('#1e40af');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('school_website_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 180)->unique();
            $table->longText('content');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('school_website_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 180)->unique();
            $table->string('category', 80)->nullable();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content');
            $table->string('cover_image_url', 2048)->nullable();
            $table->string('status', 20)->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 180)->unique();
            $table->text('description');
            $table->string('venue', 180)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('registration_open')->default(true);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'starts_at']);
        });

        Schema::create('school_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('school_events')->cascadeOnDelete();
            $table->string('attendee_name', 180);
            $table->string('email', 180)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('attendee_type', 30)->default('guest');
            $table->string('status', 20)->default('registered');
            $table->string('attendance_status', 20)->default('not_marked');
            $table->timestamp('registered_at');
            $table->timestamps();
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'email']);
        });

        Schema::create('school_clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('meeting_schedule', 180)->nullable();
            $table->foreignId('patron_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['status', 'name']);
        });

        Schema::create('school_club_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('school_clubs')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('joined_at');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['club_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_club_members');
        Schema::dropIfExists('school_clubs');
        Schema::dropIfExists('school_event_registrations');
        Schema::dropIfExists('school_events');
        Schema::dropIfExists('school_website_posts');
        Schema::dropIfExists('school_website_pages');
        Schema::dropIfExists('school_website_settings');
    }
};
