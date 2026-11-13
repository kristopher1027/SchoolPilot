@extends('tenant.layouts.app')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h3 class="text-2xl font-bold text-gray-800">Payment Reconciliation</h3>
        <p class="text-sm text-gray-500">Review recorded fee payments by date and payment method.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="rounded border px-4 py-2" href="{{ url('/school/' . request()->route('school') . '/fees') }}">Back to Fees</a>
        <a class="rounded bg-blue-700 px-4 py-2 text-white" href="{{ url('/school/' . request()->route('school') . '/fees/reconciliation/export') . '?' . http_build_query(array_filter($filters)) }}">Export CSV</a>
    </div>
</div>

<form method="GET" class="mb-6 grid grid-cols-1 gap-3 rounded-lg bg-white p-5 shadow sm:grid-cols-4">
    <div>
        <label for="from" class="mb-1 block text-sm font-medium text-gray-700">From date</label>
        <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="w-full rounded border-gray-300">
    </div>
    <div>
        <label for="to" class="mb-1 block text-sm font-medium text-gray-700">To date</label>
        <input id="to" name="to" type="date" value="{{ $filters['to'] }}" class="w-full rounded border-gray-300">
    </div>
    <div>
        <label for="method" class="mb-1 block text-sm font-medium text-gray-700">Payment method</label>
        <select id="method" name="method" class="w-full rounded border-gray-300">
            <option value="">All methods</option>
            @foreach(['cash', 'transfer', 'paystack', 'flutterwave'] as $method)
                <option value="{{ $method }}" @selected($filters['method'] === $method)>{{ ucfirst($method) }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end gap-2">
        <button class="rounded bg-gray-800 px-4 py-2 text-white" type="submit">Apply filters</button>
        <a class="rounded border px-4 py-2" href="{{ url('/school/' . request()->route('school') . '/fees/reconciliation') }}">Reset</a>
    </div>
</form>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Payments in selected period</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">{{ number_format($summary['count']) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Total collected in selected period</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">₦{{ number_format($summary['collected'], 2) }}</p>
    </div>
</div>

<div class="mb-6 overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b p-5"><h4 class="font-semibold text-gray-800">Totals by payment method</h4></div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr><th class="px-4 py-3">Method</th><th class="px-4 py-3">Number of payments</th><th class="px-4 py-3">Amount collected</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($byMethod as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm capitalize">{{ $row->method }}</td>
                        <td class="px-4 py-3 text-sm">{{ number_format($row->payment_count) }}</td>
                        <td class="px-4 py-3 text-sm font-semibold">₦{{ number_format((float) $row->amount_total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-gray-500">No payments match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b p-5"><h4 class="font-semibold text-gray-800">Payment records</h4><p class="text-sm text-gray-500">Each row represents a recorded payment, not an invoice.</p></div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Student</th><th class="px-4 py-3">Receipt</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($payments as $payment)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">{{ \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d M Y, h:i A') }}</td>
                        <td class="px-4 py-3 text-sm font-medium">{{ $payment->last_name }}, {{ $payment->first_name }}<div class="text-xs text-gray-500">{{ $payment->admission_no }}</div></td>
                        <td class="px-4 py-3 text-sm">{{ $payment->receipt_no }}</td>
                        <td class="px-4 py-3 text-sm">{{ $payment->reference ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm capitalize">{{ $payment->method }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold">₦{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm"><a class="text-blue-700 hover:underline" href="{{ url('/school/' . request()->route('school') . '/fees/receipts/' . $payment->id) }}">Receipt</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">No payment records found for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t p-4">{{ $payments->links() }}</div>
</div>
@endsection
