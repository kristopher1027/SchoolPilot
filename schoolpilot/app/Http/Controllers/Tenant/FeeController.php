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
