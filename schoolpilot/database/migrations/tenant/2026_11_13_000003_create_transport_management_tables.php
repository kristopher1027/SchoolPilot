<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_no', 40)->unique();
            $table->string('make', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->string('colour', 40)->nullable();
            $table->string('vehicle_type', 40)->default('bus');
            $table->unsignedSmallInteger('seat_capacity');
            $table->string('status', 24)->default('active');
            $table->date('insurance_expires_at')->nullable();
            $table->date('roadworthiness_expires_at')->nullable();
            $table->date('licence_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'registration_no']);
        });

        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_code', 40)->unique();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('transport_vehicles')->nullOnDelete();
            $table->foreignId('driver_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('attendant_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->decimal('transport_fee', 12, 2)->default(0);
            $table->string('fee_frequency', 20)->default('term');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['status', 'name']);
        });

        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->text('address')->nullable();
            $table->text('landmark')->nullable();
            $table->timestamps();
            $table->unique(['name', 'address']);
        });

        Schema::create('transport_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignId('stop_id')->constrained('transport_stops')->restrictOnDelete();
            $table->unsignedSmallInteger('stop_order');
            $table->time('pickup_time')->nullable();
            $table->time('dropoff_time')->nullable();
            $table->timestamps();
            $table->unique(['route_id', 'stop_id']);
            $table->unique(['route_id', 'stop_order']);
        });

        Schema::create('transport_student_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('route_id')->constrained('transport_routes')->restrictOnDelete();
            $table->foreignId('pickup_stop_id')->constrained('transport_stops')->restrictOnDelete();
            $table->foreignId('dropoff_stop_id')->constrained('transport_stops')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('fee_override', 12, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index(['route_id', 'status']);
        });

        Schema::create('transport_vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('document_number', 100)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'expires_at']);
        });

        Schema::create('transport_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('transport_routes')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('transport_vehicles')->restrictOnDelete();
            $table->foreignId('driver_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('trip_date');
            $table->string('trip_type', 12)->default('pickup');
            $table->string('status', 20)->default('scheduled');
            $table->time('departed_at')->nullable();
            $table->time('arrived_at')->nullable();
            $table->unsignedInteger('odometer_start')->nullable();
            $table->unsignedInteger('odometer_end')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['trip_date', 'route_id', 'trip_type']);
        });

        Schema::create('transport_trip_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('transport_trips')->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained('transport_student_assignments')->restrictOnDelete();
            $table->string('attendance_status', 20)->default('boarded');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['trip_id', 'assignment_id']);
        });

        Schema::create('transport_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->nullable()->constrained('transport_trips')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('transport_routes')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('transport_vehicles')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->string('severity', 20)->default('low');
            $table->string('incident_type', 60);
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['occurred_at', 'severity']);
        });

        Schema::create('transport_vehicle_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->string('maintenance_type', 80);
            $table->text('description');
            $table->decimal('cost', 12, 2)->default(0);
            $table->date('service_date');
            $table->date('next_service_date')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('provider', 140)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['vehicle_id', 'next_service_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_vehicle_maintenance');
        Schema::dropIfExists('transport_incidents');
        Schema::dropIfExists('transport_trip_attendance');
        Schema::dropIfExists('transport_trips');
        Schema::dropIfExists('transport_vehicle_documents');
        Schema::dropIfExists('transport_student_assignments');
        Schema::dropIfExists('transport_route_stops');
        Schema::dropIfExists('transport_stops');
        Schema::dropIfExists('transport_routes');
        Schema::dropIfExists('transport_vehicles');
    }
};
