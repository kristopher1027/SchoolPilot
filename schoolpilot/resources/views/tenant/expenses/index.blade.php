@extends('tenant.layouts.app')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div><h1 class="text-2xl font-bold text-gray-800">School Expenses</h1><p class="text-sm text-gray-500">Track spending, vendors, payments and outstanding expenses.</p></div>
    <a class="rounded border px-4 py-2 text-sm" href="{{ url('/school/'.request()->route('school').'/expenses/export') }}?{{ http_build_query($filters) }}">Export CSV</a>
</div>
@if(session('success'))<div class="mb-4 rounded border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-red-700"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
@foreach(['total'=>'Total expenses','paid'=>'Paid','unpaid'=>'Unpaid'] as $key=>$label)<div class="rounded-lg bg-white p-5 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-bold">₦{{ number_format($summary[$key],2) }}</p></div>@endforeach
</div>
<div class="mb-6 rounded-lg bg-white p-5 shadow"><h2 class="mb-4 font-semibold">Filter expenses</h2>
<form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
<label class="text-sm">From<input type="date" name="from" value="{{ $filters['from'] }}" class="mt-1 w-full rounded border p-2"></label>
<label class="text-sm">To<input type="date" name="to" value="{{ $filters['to'] }}" class="mt-1 w-full rounded border p-2"></label>
<label class="text-sm">Category<select name="category_id" class="mt-1 w-full rounded border p-2"><option value="">All</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($filters['category_id']==$category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label class="text-sm">Status<select name="status" class="mt-1 w-full rounded border p-2"><option value="">All</option><option value="paid" @selected($filters['status']=='paid')>Paid</option><option value="unpaid" @selected($filters['status']=='unpaid')>Unpaid</option></select></label>
<div class="flex items-end gap-2"><button class="rounded bg-blue-700 px-4 py-2 text-white">Apply</button><a href="{{ url('/school/'.request()->route('school').'/expenses') }}" class="rounded border px-4 py-2">Reset</a></div>
</form></div>
<div class="mb-6 rounded-lg bg-white p-5 shadow"><h2 class="mb-4 font-semibold">Record expense</h2>
<form method="POST" action="{{ url('/school/'.request()->route('school').'/expenses') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf
<label class="text-sm">Category<select required name="category_id" class="mt-1 w-full rounded border p-2"><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
<label class="text-sm">Description<input required maxlength="180" name="description" class="mt-1 w-full rounded border p-2"></label>
<label class="text-sm">Vendor / Payee<input maxlength="160" name="vendor" class="mt-1 w-full rounded border p-2"></label>
<label class="text-sm">Amount (₦)<input required type="number" step="0.01" min="0.01" name="amount" class="mt-1 w-full rounded border p-2"></label>
<label class="text-sm">Expense date<input required type="date" name="expense_date" value="{{ today()->format('Y-m-d') }}" class="mt-1 w-full rounded border p-2"></label>
<label class="text-sm">Payment method<select required name="payment_method" class="mt-1 w-full rounded border p-2"><option value="cash">Cash</option><option value="transfer">Transfer</option><option value="card">Card</option><option value="other">Other</option></select></label>
<label class="text-sm">Status<select required name="status" class="mt-1 w-full rounded border p-2"><option value="paid">Paid</option><option value="unpaid">Unpaid</option></select></label>
<label class="text-sm">Paid date (optional)<input type="date" name="paid_at" class="mt-1 w-full rounded border p-2"></label>
<label class="text-sm">Reference<input maxlength="100" name="reference" class="mt-1 w-full rounded border p-2"></label>
<label class="text-sm lg:col-span-3">Notes<textarea name="notes" maxlength="2000" rows="2" class="mt-1 w-full rounded border p-2"></textarea></label>
<div class="flex items-end"><button class="rounded bg-blue-700 px-4 py-2 text-white">Save expense</button></div>
</form></div>
<div class="overflow-hidden rounded-lg bg-white shadow"><div class="overflow-x-auto"><table class="min-w-full divide-y"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="p-3">Date</th><th class="p-3">Category</th><th class="p-3">Description / vendor</th><th class="p-3">Amount</th><th class="p-3">Status</th><th class="p-3">Reference</th><th class="p-3">Action</th></tr></thead><tbody class="divide-y">
@forelse($expenses as $expense)<tr><td class="p-3 text-sm">{{ $expense->expense_date?->format('d M Y') }}</td><td class="p-3 text-sm">{{ $expense->category?->name }}</td><td class="p-3 text-sm">{{ $expense->description }}<div class="text-xs text-gray-500">{{ $expense->vendor }}</div></td><td class="p-3 text-sm font-semibold">₦{{ number_format($expense->amount,2) }}</td><td class="p-3 text-sm capitalize">{{ $expense->status }}</td><td class="p-3 text-sm">{{ $expense->reference ?? '—' }}</td><td class="p-3">@if($expense->status==='unpaid')<form method="POST" action="{{ url('/school/'.request()->route('school').'/expenses/'.$expense->id.'/mark-paid') }}">@csrf<input type="hidden" name="paid_at" value="{{ today()->format('Y-m-d') }}"><input type="hidden" name="payment_method" value="transfer"><button class="text-sm text-blue-700 underline">Mark paid</button></form>@else<span class="text-xs text-gray-400">—</span>@endif</td></tr>
@empty<tr><td colspan="7" class="p-8 text-center text-gray-500">No expenses recorded for this filter.</td></tr>@endforelse</tbody></table></div><div class="p-4">{{ $expenses->links() }}</div></div>
@endsection
