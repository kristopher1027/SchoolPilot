<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overdue_invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->date('reminder_date');
            $table->string('recipient_email', 255);
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(
                ['invoice_id', 'reminder_date', 'recipient_email'],
                'overdue_reminders_invoice_day_recipient_unique'
            );
            $table->index(['status', 'reminder_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overdue_invoice_reminders');
    }
};
