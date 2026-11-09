<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central_sqlite';

    public function up(): void
    {
        Schema::connection($this->connection)->create('provisioning_logs', function (Blueprint $table) {
            $table->id();
            // Tenant IDs are UUID strings; intentionally no FK so logs survive school deletion.
            $table->string('school_id')->nullable();
            $table->foreignId('performed_by')->nullable();
            $table->enum('action', ['create', 'suspend', 'activate', 'migrate', 'impersonate']);
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('provisioning_logs');
    }
};
