@extends('tenant.layouts.app')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h3 class="text-2xl font-bold text-gray-800">Outstanding Invoice Aging</h3>
        <p class="text-sm text-gray-500">See how long unpaid invoices have remained overdue.</p>
    </div>
    <div class="flex gap-2">
        <a class="rounded border px-4 py-2" href="{{ url('/school/' . request()->route('school') . '/fees') }}">Back to Fees</a>
        <a class="rounded border px-4 py-2" href="{{ url('/school/' . request()->route('school') . '/fees/overdue') }}">Overdue Invoices</a>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
    @foreach(['1-30 days', '31-60 days', '61-90 days', 'Over 90 days'] as $bucket)
        <div class="rounded-lg bg-white p-5 shadow">
            <p class="text-sm text-gray-500">{{ $bucket }}</p>
            <p class="mt-1 text-xl font-bold text-gray-800">₦{{ number_format($summary[$bucket]['balance'], 2) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ number_format($summary[$bucket]['count']) }} invoices</p>
        </div>
    @endforeach
    <div class="rounded-lg bg-blue-50 p-5 shadow">
        <p class="text-sm text-blue-700">Total overdue</p>
        <p class="mt-1 text-xl font-bold text-blue-900">₦{{ number_format($summary['total']['balance'], 2) }}</p>
        <p class="mt-1 text-xs text-blue-700">{{ number_format($summary['total']['count']) }} invoices</p>
    </div>
</div>

<div class="overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b p-5">
        <h4 class="font-semibold text-gray-800">Overdue invoice details</h4>
        <p class="text-sm text-gray-500">Invoices are grouped by days past their due date.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Student</th>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Due date</th>
                    <th class="px-4 py-3">Age bracket</th>
                    <th class="px-4 py-3">Invoice total</th>
                    <th class="px-4 py-3">Paid</th>
                    <th class="px-4 py-3">Outstanding</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($invoices as $invoice)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium">{{ $invoice->last_name }}, {{ $invoice->first_name }}<div class="text-xs text-gray-500">{{ $invoice->admission_no }}</div></td>
                        <td class="px-4 py-3 text-sm">#{{ $invoice->id }}</td>
                        <td class="px-4 py-3 text-sm whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->age_bucket }}</td>
                        <td class="px-4 py-3 text-sm">₦{{ number_format((float) $invoice->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm">₦{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                        <td class="px-4 py-3 text-sm font-semibold">₦{{ number_format((float) $invoice->balance, 2) }}</td>
                        <td class="px-4 py-3 text-sm"><a class="text-blue-700 hover:underline" href="{{ url('/school/' . request()->route('school') . '/fees/invoices/' . $invoice->id) }}">Open invoice</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-8 text-center text-gray-500">No overdue balances to report.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t p-4">{{ $invoices->links() }}</div>
</div>
@endsection
