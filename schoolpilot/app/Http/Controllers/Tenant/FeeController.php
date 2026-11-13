<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\FeeItem;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Payment;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Staff;
use App\Models\Tenant\Section;
use App\Models\Tenant\Student;
use App\Models\Tenant\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['student', 'term', 'academicSession'])
            ->latest()->paginate(20);
        $feeItems = FeeItem::with(['academicSession', 'term', 'schoolClass', 'section'])
            ->latest()->get();
        $summary = [
            'invoiced' => (float) Invoice::sum('total_amount'),
            'collected' => (float) Invoice::sum('amount_paid'),
            'outstanding' => (float) Invoice::sum('balance'),
        ];

        return view('tenant.fees.index', compact('invoices', 'feeItems', 'summary'));
    }

    public function reconciliation(Request $request)
    {
        $filters = $this->validatedReconciliationFilters($request);
        $paymentsQuery = $this->reconciliationQuery($filters);
        $summary = [
            'count' => (clone $paymentsQuery)->count(),
            'collected' => round((float) (clone $paymentsQuery)->sum('p.amount'), 2),
        ];
        $byMethod = (clone $paymentsQuery)
            ->select('p.method')
            ->selectRaw('COUNT(p.id) as payment_count, SUM(p.amount) as amount_total')
            ->groupBy('p.method')
            ->orderBy('p.method')
            ->get();
        $payments = $paymentsQuery
            ->select([
                'p.id', 'p.amount', 'p.method', 'p.reference', 'p.receipt_no', 'p.paid_at',
                'i.id as invoice_id', 's.admission_no', 's.first_name', 's.last_name',
            ])
            ->orderByDesc('p.paid_at')
            ->orderByDesc('p.id')
            ->paginate(25)
            ->withQueryString();

        return view('tenant.fees.reconciliation', compact('filters', 'summary', 'byMethod', 'payments'));
    }

    public function exportReconciliation(Request $request): StreamedResponse
    {
        $filters = $this->validatedReconciliationFilters($request);
        $payments = $this->reconciliationQuery($filters)
            ->join('academic_sessions as a', 'a.id', '=', 'i.academic_session_id')
            ->join('terms as t', 't.id', '=', 'i.term_id')
            ->select([
                'p.paid_at', 'p.receipt_no', 'p.reference', 'p.method', 'p.amount',
                's.admission_no', 's.first_name', 's.last_name', 'i.id as invoice_id',
                'a.name as session_name', 't.name as term_name',
            ])
            ->orderBy('p.paid_at')
            ->orderBy('p.id')
            ->get();

        $this->audit('fee_reconciliation.exported', 'fee_reconciliation', 0, null, $filters);
        $filename = 'schoolpilot-fee-reconciliation-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($payments) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Paid at', 'Receipt number', 'Payment reference', 'Method', 'Amount (NGN)', 'Admission number', 'Student', 'Invoice ID', 'Academic session', 'Term']);
            foreach ($payments as $payment) {
                $values = [
                    $payment->paid_at,
                    $payment->receipt_no,
                    $payment->reference,
                    $payment->method,
                    $payment->amount,
                    $payment->admission_no,
                    trim($payment->first_name . ' ' . $payment->last_name),
                    $payment->invoice_id,
                    $payment->session_name,
                    $payment->term_name,
                ];
                fputcsv($output, array_map(fn ($value) => $this->safeCsvValue($value), $values));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedReconciliationFilters(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'method' => ['nullable', Rule::in(['cash', 'transfer', 'paystack', 'flutterwave'])],
        ]);

        return array_merge(['from' => null, 'to' => null, 'method' => null], $filters);
    }

    private function reconciliationQuery(array $filters)
    {
        return DB::table('payments as p')
            ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->join('students as s', 's.id', '=', 'i.student_id')
            ->when($filters['from'], fn ($query) => $query->whereDate('p.paid_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($query) => $query->whereDate('p.paid_at', '<=', $filters['to']))
            ->when($filters['method'], fn ($query) => $query->where('p.method', $filters['method']));
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+@\\-\\t\\r]/', $value) ? "'" . $value : $value;
    }

    public function invoiceAging()
    {
        $today = today()->toDateString();
        $daysOverdue = 'CAST(julianday(?) - julianday(i.due_date) AS INTEGER)';
        $bucket = "CASE
            WHEN {$daysOverdue} BETWEEN 1 AND 30 THEN '1-30 days'
            WHEN {$daysOverdue} BETWEEN 31 AND 60 THEN '31-60 days'
            WHEN {$daysOverdue} BETWEEN 61 AND 90 THEN '61-90 days'
            ELSE 'Over 90 days'
        END";

        $baseQuery = DB::table('invoices as i')
            ->join('students as s', 's.id', '=', 'i.student_id')
            ->whereNotNull('i.due_date')
            ->whereDate('i.due_date', '<', $today)
            ->where('i.balance', '>', 0)
            ->where('i.status', '!=', 'paid');

        $aging = (clone $baseQuery)
            ->selectRaw("{$bucket} as age_bucket, COUNT(i.id) as invoice_count, SUM(i.balance) as outstanding_total", [$today, $today, $today])
            ->groupBy('age_bucket')
            ->get()
            ->keyBy('age_bucket');

        $labels = ['1-30 days', '31-60 days', '61-90 days', 'Over 90 days'];
        $summary = collect($labels)->mapWithKeys(function ($label) use ($aging) {
            $row = $aging->get($label);

            return [$label => [
                'count' => (int) ($row->invoice_count ?? 0),
                'balance' => round((float) ($row->outstanding_total ?? 0), 2),
            ]];
        });
        $summary['total'] = [
            'count' => (int) (clone $baseQuery)->count(),
            'balance' => round((float) (clone $baseQuery)->sum('i.balance'), 2),
        ];

        $invoices = (clone $baseQuery)
            ->select([
                'i.id', 'i.due_date', 'i.balance', 'i.total_amount', 'i.amount_paid',
                's.first_name', 's.last_name', 's.admission_no',
            ])
            ->selectRaw("{$bucket} as age_bucket", [$today, $today, $today])
            ->orderBy('i.due_date')
            ->paginate(25)
            ->withQueryString();

        return view('tenant.fees.aging', compact('summary', 'invoices'));
    }

    public function overdueInvoices()
    {
        $overdueQuery = Invoice::with([
            'student.guardians' => fn ($query) => $query->wherePivot('is_primary', true),
            'academicSession',
            'term',
        ])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->where('balance', '>', 0)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date');

        $summary = [
            'count' => (clone $overdueQuery)->count(),
            'balance' => round((float) (clone $overdueQuery)->sum('balance'), 2),
        ];
        $invoices = $overdueQuery->paginate(25)->withQueryString();

        return view('tenant.fees.overdue', compact('invoices', 'summary'));
    }

    public function createFeeItem()
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $terms = Term::with('academicSession')->orderBy('start_date')->get();
        $classes = SchoolClass::with('section')->orderBy('name')->get();
        $sections = Section::orderBy('name')->get();

        return view('tenant.fees.create-item', compact('sessions', 'terms', 'classes', 'sections'));
    }

    public function storeFeeItem(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        $term = Term::findOrFail($data['term_id']);
        if ((int) $term->academic_session_id !== (int) $data['academic_session_id']) {
            throw ValidationException::withMessages(['term_id' => 'The selected term does not belong to the selected academic session.']);
        }

        $class = isset($data['class_id']) ? SchoolClass::findOrFail($data['class_id']) : null;
        if ($class && ! empty($data['section_id']) && (int) $class->section_id !== (int) $data['section_id']) {
            throw ValidationException::withMessages(['class_id' => 'The selected class does not belong to the selected section.']);
        }
        $sectionId = $class?->section_id ?? ($data['section_id'] ?? null);

        $item = DB::transaction(function () use ($data, $class, $sectionId) {
            $item = FeeItem::create([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'academic_session_id' => $data['academic_session_id'],
            'term_id' => $data['term_id'],
            'class_id' => $class?->id,
                'section_id' => $sectionId,
            ]);
            $this->audit('fee_item.created', 'fee_item', $item->id, null, $item->only(['name', 'amount', 'academic_session_id', 'term_id', 'class_id', 'section_id']));
            return $item;
        });

        return redirect($this->schoolPath($request, '/fees'))->with('success', 'Fee item created successfully.');
    }

    public function createInvoice()
    {
        $students = Student::with(['schoolClass', 'section'])
            ->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get();
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $terms = Term::with('academicSession')->orderBy('start_date')->get();
        $feeItems = FeeItem::with(['schoolClass', 'section', 'term'])->orderBy('name')->get();

        return view('tenant.fees.create-invoice', compact('students', 'sessions', 'terms', 'feeItems'));
    }

    public function storeInvoice(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'fee_item_ids' => ['required', 'array', 'min:1'],
            'fee_item_ids.*' => ['required', 'integer', 'distinct', 'exists:fee_items,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $student = Student::where('status', 'active')->findOrFail($data['student_id']);
        $term = Term::findOrFail($data['term_id']);
        if ((int) $term->academic_session_id !== (int) $data['academic_session_id']) {
            throw ValidationException::withMessages(['term_id' => 'The selected term does not belong to the selected academic session.']);
        }

        $items = FeeItem::whereIn('id', $data['fee_item_ids'])
            ->where('academic_session_id', $data['academic_session_id'])
            ->where('term_id', $data['term_id'])
            ->get();

        if ($items->count() !== count($data['fee_item_ids'])) {
            throw ValidationException::withMessages(['fee_item_ids' => 'One or more fee items do not belong to the selected session and term.']);
        }

        foreach ($items as $item) {
            if ($item->class_id && (int) $item->class_id !== (int) $student->class_id) {
                throw ValidationException::withMessages(['fee_item_ids' => 'A selected fee item does not apply to this student’s class.']);
            }
            if ($item->section_id && (int) $item->section_id !== (int) $student->section_id) {
                throw ValidationException::withMessages(['fee_item_ids' => 'A selected fee item does not apply to this student’s section.']);
            }
        }

        $invoice = DB::transaction(function () use ($data, $student, $items) {
            $total = round((float) $items->sum('amount'), 2);
            $invoice = Invoice::create([
                'student_id' => $student->id,
                'academic_session_id' => $data['academic_session_id'],
                'term_id' => $data['term_id'],
                'total_amount' => $total,
                'amount_paid' => 0,
                'balance' => $total,
                'status' => 'unpaid',
                'due_date' => $data['due_date'] ?? null,
            ]);

            foreach ($items as $item) {
                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoice->id,
                    'fee_item_id' => $item->id,
                    'amount' => $item->amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->audit('invoice.created', 'invoice', $invoice->id, null, [
                'student_id' => $student->id,
                'total_amount' => $invoice->total_amount,
                'term_id' => $invoice->term_id,
            ]);
            return $invoice;
        });

        return redirect($this->schoolPath($request, '/fees/invoices/' . $invoice->id))
            ->with('success', 'Invoice issued successfully.');
    }

    public function showInvoice(Invoice $invoice)
    {
        $invoice->load(['student.schoolClass', 'student.section', 'academicSession', 'term', 'items.feeItem', 'payments']);

        return view('tenant.fees.show-invoice', compact('invoice'));
    }

    public function storePayment(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'method' => ['required', Rule::in(['cash', 'transfer'])],
            'reference' => ['nullable', 'string', 'max:120', Rule::requiredIf(fn () => $request->input('method') === 'transfer'), Rule::unique('payments', 'reference')],
        ]);

        $payment = DB::transaction(function () use ($data, $invoice) {
            $amount = round((float) $data['amount'], 2);
            $sqlAmount = number_format($amount, 2, '.', '');

            // Apply the balance check and update in one SQL statement. This is
            // safe on SQLite too, where lockForUpdate() does not provide row locks.
            $updated = Invoice::whereKey($invoice->id)
                ->where('status', '!=', 'paid')
                ->where('balance', '>=', $amount)
                ->increment('amount_paid', $amount, [
                    'balance' => DB::raw("balance - {$sqlAmount}"),
                    'status' => DB::raw("CASE WHEN balance - {$sqlAmount} <= 0 THEN 'paid' ELSE 'partial' END"),
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'amount' => 'The invoice is already paid or the payment exceeds its current outstanding balance.',
                ]);
            }

            $lockedInvoice = Invoice::findOrFail($invoice->id);
            $staffId = Staff::where('user_id', Auth::guard('school')->id())->value('id');
            $payment = Payment::create([
                'invoice_id' => $lockedInvoice->id,
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
                'recorded_by' => $staffId,
                'receipt_no' => $this->receiptNumber(),
            ]);

            $this->audit('payment.recorded', 'payment', $payment->id, null, [
                'invoice_id' => $lockedInvoice->id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'receipt_no' => $payment->receipt_no,
            ]);
            return $payment;
        });

        return redirect($this->schoolPath($request, '/fees/receipts/' . $payment->id))
            ->with('success', 'Payment recorded. Receipt generated.');
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['invoice.student', 'invoice.academicSession', 'invoice.term']);

        return view('tenant.fees.receipt', compact('payment'));
    }

    private function receiptNumber(): string
    {
        do {
            $number = 'SP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (Payment::where('receipt_no', $number)->exists());

        return $number;
    }

    private function audit(string $action, string $type, int $id, ?array $old, ?array $new): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::guard('school')->id(),
            'action' => $action,
            'entity_type' => $type,
            'entity_id' => $id,
            'old_value' => $old ? json_encode($old) : null,
            'new_value' => $new ? json_encode($new) : null,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function schoolPath(Request $request, string $path): string
    {
        return '/school/' . $request->route('school') . $path;
    }
}
