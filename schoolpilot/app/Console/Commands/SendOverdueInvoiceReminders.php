<?php

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOverdueInvoiceReminders extends Command
{
    protected $signature = 'schoolpilot:send-overdue-reminders {--dry-run : List reminders without sending email}';

    protected $description = 'Email primary guardians about overdue school invoices across active tenants';

    public function handle(): int
    {
        if (! $this->option('dry-run') && in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->components->error('Email reminders were not sent: configure a real MAIL_MAILER before running this command.');
            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->where('status', 'active')
            ->get(['id', 'name', 'slug', 'db_name']);

        $previousDefault = DB::getDefaultConnection();
        $previousDatabase = Config::get('database.connections.tenant_sqlite.database');
        $sent = 0;
        $failed = 0;

        try {
            foreach ($tenants as $tenant) {
                $databaseName = basename((string) $tenant->db_name);
                $databasePath = database_path('tenants/' . $databaseName);

                if (! $tenant->db_name || ! is_file($databasePath) || $databaseName !== $tenant->db_name) {
                    $this->components->warn("Skipped tenant {$tenant->slug}: tenant database is missing or invalid.");
                    continue;
                }

                Config::set('database.connections.tenant_sqlite.database', $databasePath);
                DB::purge('tenant_sqlite');
                DB::setDefaultConnection('tenant_sqlite');

                $reminders = DB::connection('tenant_sqlite')->table('invoices as i')
                    ->join('students as s', 's.id', '=', 'i.student_id')
                    ->join('student_parents as sp', function ($join) {
                        $join->on('sp.student_id', '=', 's.id')->where('sp.is_primary', true);
                    })
                    ->join('parents as p', 'p.id', '=', 'sp.parent_id')
                    ->join('users as u', 'u.id', '=', 'p.user_id')
                    ->whereNotNull('i.due_date')
                    ->whereDate('i.due_date', '<', today())
                    ->where('i.balance', '>', 0)
                    ->where('i.status', '!=', 'paid')
                    ->whereNotNull('u.email')
                    ->where('u.email', '!=', '')
                    ->select([
                        'i.id as invoice_id', 'i.balance', 'i.due_date',
                        's.first_name', 's.last_name', 's.admission_no',
                        'u.email as recipient_email', 'u.name as guardian_name',
                    ])
                    ->orderBy('i.id')
                    ->get();

                foreach ($reminders as $reminder) {
                    if ($this->option('dry-run')) {
                        $this->line("[DRY RUN] {$tenant->slug}: invoice {$reminder->invoice_id} -> {$reminder->recipient_email}");
                        continue;
                    }

                    $log = DB::connection('tenant_sqlite')->table('overdue_invoice_reminders');
                    $today = today()->toDateString();
                    $log->insertOrIgnore([
                        'invoice_id' => $reminder->invoice_id,
                        'reminder_date' => $today,
                        'recipient_email' => $reminder->recipient_email,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $record = $log->where('invoice_id', $reminder->invoice_id)
                        ->where('reminder_date', $today)
                        ->where('recipient_email', $reminder->recipient_email)
                        ->first();

                    if ($record?->status === 'sent') {
                        continue;
                    }

                    try {
                        $studentName = trim($reminder->first_name . ' ' . $reminder->last_name);
                        Mail::raw(
                            "Dear {$reminder->guardian_name},\n\nThis is a reminder that the following school fee invoice is overdue.\n\nSchool: {$tenant->name}\nStudent: {$studentName} ({$reminder->admission_no})\nInvoice: #{$reminder->invoice_id}\nDue date: {$reminder->due_date}\nOutstanding balance: NGN " . number_format((float) $reminder->balance, 2) . "\n\nPlease contact the school bursary for payment arrangements. If you have already paid, kindly disregard this notice and contact the school with your receipt details.\n\nRegards,\n{$tenant->name}",
                            function ($message) use ($reminder, $tenant) {
                                $message->to($reminder->recipient_email)
                                    ->subject($tenant->name . ' – Overdue school fee reminder');
                            }
                        );

                        $log->where('invoice_id', $reminder->invoice_id)
                            ->where('reminder_date', $today)
                            ->where('recipient_email', $reminder->recipient_email)
                            ->update(['status' => 'sent', 'sent_at' => now(), 'failure_reason' => null, 'updated_at' => now()]);
                        $sent++;
                    } catch (Throwable $exception) {
                        $log->where('invoice_id', $reminder->invoice_id)
                            ->where('reminder_date', $today)
                            ->where('recipient_email', $reminder->recipient_email)
                            ->update(['status' => 'failed', 'failure_reason' => mb_substr($exception->getMessage(), 0, 2000), 'updated_at' => now()]);
                        $failed++;
                        report($exception);
                    }
                }

                DB::purge('tenant_sqlite');
            }
        } finally {
            DB::setDefaultConnection($previousDefault);
            DB::purge('tenant_sqlite');
            Config::set('database.connections.tenant_sqlite.database', $previousDatabase);
        }

        $this->components->info("Overdue reminders sent: {$sent}; failed: {$failed}.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
