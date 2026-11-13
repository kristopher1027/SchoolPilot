<?php

namespace Tests\Feature;

use App\Console\Commands\SendOverdueInvoiceReminders;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class OverdueReminderClaimTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::connection('sqlite')->create('overdue_invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->date('reminder_date');
            $table->string('recipient_email');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['invoice_id', 'reminder_date', 'recipient_email']);
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_a_reminder_can_only_be_claimed_once_while_sending(): void
    {
        $this->insertReminder('pending');
        $this->assertTrue($this->claim());
        $this->assertFalse($this->claim());
        $this->assertSame('sending', DB::table('overdue_invoice_reminders')->value('status'));
    }

    public function test_failed_reminders_can_be_retried(): void
    {
        $this->insertReminder('failed');
        $this->assertTrue($this->claim());
        $this->assertSame('sending', DB::table('overdue_invoice_reminders')->value('status'));
    }

    public function test_stale_sending_claims_can_be_recovered_after_thirty_minutes(): void
    {
        $this->insertReminder('sending', now()->subMinutes(31));
        $this->assertTrue($this->claim());
        $this->assertSame('sending', DB::table('overdue_invoice_reminders')->value('status'));
        $this->assertNull(DB::table('overdue_invoice_reminders')->value('failure_reason'));
    }

    public function test_sent_reminders_cannot_be_claimed_again(): void
    {
        $this->insertReminder('sent');
        $this->assertFalse($this->claim());
        $this->assertSame('sent', DB::table('overdue_invoice_reminders')->value('status'));
    }

    private function insertReminder(string $status, $updatedAt = null): void
    {
        DB::table('overdue_invoice_reminders')->insert([
            'invoice_id' => 42,
            'reminder_date' => today()->toDateString(),
            'recipient_email' => 'guardian@example.test',
            'status' => $status,
            'failure_reason' => $status === 'failed' ? 'Temporary mail error' : null,
            'created_at' => now(),
            'updated_at' => $updatedAt ?? now(),
        ]);
    }

    private function claim(): bool
    {
        $command = new SendOverdueInvoiceReminders();
        $method = new ReflectionMethod($command, 'claimReminder');

        return $method->invoke(
            $command,
            DB::table('overdue_invoice_reminders'),
            42,
            today()->toDateString(),
            'guardian@example.test'
        );
    }
}
