<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable(); // We won't use strict foreign keys here so we can keep logs even if a school is deleted
            $table->foreignId('performed_by')->nullable();
            $table->enum('action', ['create', 'suspend', 'activate', 'migrate', 'impersonate']);
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_logs');
    }
};