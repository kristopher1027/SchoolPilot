<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_parents', function (Blueprint $table): void {
            $table->string('relationship', 100)->nullable();
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('can_pickup')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('student_parents', function (Blueprint $table): void {
            $table->dropColumn(['relationship', 'is_emergency_contact', 'can_pickup']);
        });
    }
};
