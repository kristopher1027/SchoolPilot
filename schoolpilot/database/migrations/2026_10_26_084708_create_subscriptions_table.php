<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central_sqlite';

    public function up(): void
    {
        Schema::connection($this->connection)->create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('school_id');
            $table->foreign('school_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->string('plan');
            $table->decimal('amount', 12, 2);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('subscriptions');
    }
};
