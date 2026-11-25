<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\SchoolExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'status' => ['nullable', Rule::in(['paid', 'unpaid'])],
        ]);
        $filters = array_merge(['from' => null, 'to' => null, 'category_id' => null, 'status' => null], $filters);

        $query = SchoolExpense::with('category')
            ->when($filters['from'], fn ($q) => $q->whereDate('expense_date', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('expense_date', '<=', $filters['to']))
            ->when($filters['category_id'], fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when($filters['status'], fn ($q) => $q->where('status', $filters['status']));
        $summaryQuery = clone $query;
        $summary = [
            'count' => (clone $summaryQuery)->count(),
            'total' => round((float) (clone $summaryQuery)->sum('amount'), 2),
            'paid' => round((float) (clone $summaryQuery)->where('status', 'paid')->sum('amount'), 2),
            'unpaid' => round((float) (clone $summaryQuery)->where('status', 'unpaid')->sum('amount'), 2),
        ];
        $expenses = $query->orderByDesc('expense_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $categories = ExpenseCategory::where('is_active', true)->orderBy('name')->get();

        return view('tenant.expenses.index', compact('expenses', 'categories', 'filters', 'summary'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'max:180'],
            'vendor' => ['nullable', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:100', 'unique:school_expenses,reference'],
            'expense_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['paid', 'unpaid'])],
            'paid_at' => ['nullable', 'date', 'required_if:status,paid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['description'] = trim($data['description']);
        $data['vendor'] = isset($data['vendor']) ? trim($data['vendor']) : null;
        $data['reference'] = isset($data['reference']) ? trim($data['reference']) : null;
        if ($data['status'] === 'paid' && empty($data['paid_at'])) {
            $data['paid_at'] = $data['expense_date'];
        }
        if ($data['status'] === 'unpaid') {
            $data['paid_at'] = null;
        }
        $data['recorded_by'] = Auth::guard('school')->id();

        DB::transaction(function () use ($data, $request): void {
            $expense = SchoolExpense::create($data);
            DB::table('audit_logs')->insert([
                'user_id' => Auth::guard('school')->id(),
                'action' => 'expense.created',
                'entity_type' => 'school_expense',
                'entity_id' => $expense->id,
                'old_value' => null,
                'new_value' => json_encode($expense->only(['category_id','description','vendor','amount','payment_method','reference','expense_date','status','paid_at'])),
                'ip_address' => $request->ip(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function markPaid(Request $request, SchoolExpense $expense)
    {
        abort_unless($expense->status === 'unpaid', 404);
        $data = $request->validate(['paid_at' => ['required', 'date'], 'payment_method' => ['required', Rule::in(['cash','transfer','card','other'])], 'reference' => ['nullable', 'string', 'max:100']]);
        if (!empty($data['reference'])) {
            validator($data, ['reference' => ['unique:school_expenses,reference']])->validate();
        }
        $old = $expense->only(['status','paid_at','payment_method','reference']);
        $expense->update(['status'=>'paid','paid_at'=>$data['paid_at'],'payment_method'=>$data['payment_method'],'reference'=>$data['reference'] ?? $expense->reference]);
        DB::table('audit_logs')->insert([
            'user_id'=>Auth::guard('school')->id(),'action'=>'expense.marked_paid','entity_type'=>'school_expense','entity_id'=>$expense->id,
            'old_value'=>json_encode($old),'new_value'=>json_encode($expense->only(['status','paid_at','payment_method','reference'])),
            'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return back()->with('success','Expense marked as paid.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate(['from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from'],'category_id'=>['nullable','integer','exists:expense_categories,id'],'status'=>['nullable',Rule::in(['paid','unpaid'])]]);
        $expenses = SchoolExpense::with('category')
            ->when($filters['from'] ?? null, fn ($q) => $q->whereDate('expense_date','>=',$filters['from']))
            ->when($filters['to'] ?? null, fn ($q) => $q->whereDate('expense_date','<=',$filters['to']))
            ->when($filters['category_id'] ?? null, fn ($q) => $q->where('category_id',$filters['category_id']))
            ->when($filters['status'] ?? null, fn ($q) => $q->where('status',$filters['status']))
            ->orderBy('expense_date')->get();
        $filename='schoolpilot-expenses-'.now()->format('Ymd-His').'.csv';
        return response()->streamDownload(function () use ($expenses) {
            $out=fopen('php://output','w');
            fputcsv($out,['Date','Category','Description','Vendor','Amount (NGN)','Status','Payment method','Paid at','Reference','Notes']);
            foreach($expenses as $expense) {
                $values=[$expense->expense_date?->format('Y-m-d'),$expense->category?->name,$expense->description,$expense->vendor,$expense->amount,$expense->status,$expense->payment_method,$expense->paid_at?->format('Y-m-d'),$expense->reference,$expense->notes];
                fputcsv($out,array_map(fn($v)=>is_string($v)&&preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v,$values));
            }
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
