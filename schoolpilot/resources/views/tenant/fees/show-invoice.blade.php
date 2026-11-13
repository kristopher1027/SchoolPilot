@extends('tenant.layouts.app')
@section('content')
<div class="mb-6">
    <a class="text-sm text-blue-700" href="{{ url('/school/' . request()->route('school') . '/fees') }}">← Fees & Payments</a>
    <h3 class="mt-2 text-2xl font-bold">Invoice #{{ $invoice->id }}</h3>
</div>
@if(session('success'))<div class="mb-5 rounded bg-green-50 p-4 text-green-800" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded bg-red-50 p-4 text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
    <div class="rounded-lg bg-white p-5 shadow md:col-span-2">
        <p class="text-sm text-gray-500">Student</p>
        <h4 class="text-lg font-semibold">{{ $invoice->student?->last_name }}, {{ $invoice->student?->first_name }} {{ $invoice->student?->middle_name }}</h4>
        <p class="text-sm text-gray-500">{{ $invoice->student?->admission_no }} · {{ $invoice->student?->schoolClass?->name }}</p>
        <p class="mt-3 text-sm">{{ $invoice->academicSession?->name }} · {{ $invoice->term?->name }}</p>
        <p class="text-sm text-gray-500">Due date: {{ $invoice->due_date?->format('d M Y') ?? 'Not set' }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Invoice status</p>
        <p class="text-xl font-bold capitalize">{{ $invoice->status }}</p>
        <p class="mt-2 text-sm">Total: ₦{{ number_format($invoice->total_amount, 2) }}</p>
        <p class="text-sm">Paid: ₦{{ number_format($invoice->amount_paid, 2) }}</p>
        <p class="text-lg font-bold">Balance: ₦{{ number_format($invoice->balance, 2) }}</p>
    </div>
</div>

<div class="mb-6 overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b p-5 font-semibold">Invoice items</div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Description</th><th class="px-4 py-3">Amount</th></tr></thead>
            <tbody class="divide-y">
                @foreach($invoice->items as $line)
                    <tr><td class="px-4 py-3 text-sm">{{ $line->feeItem?->name ?? 'Fee item' }}</td><td class="px-4 py-3 text-sm">₦{{ number_format($line->amount, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if((float) $invoice->balance > 0)
<div class="mb-6 rounded-lg bg-white p-5 shadow">
    <h4 class="mb-4 font-semibold">Record Payment</h4>
    <form method="POST" action="{{ url('/school/' . request()->route('school') . '/fees/invoices/' . $invoice->id . '/payments') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @csrf
        <div><label class="mb-1 block text-sm font-medium">Amount (₦)</label><input type="number" name="amount" min="0.01" max="{{ $invoice->balance }}" step="0.01" required value="{{ old('amount') }}" class="w-full rounded border-gray-300"></div>
        <div><label class="mb-1 block text-sm font-medium">Payment method</label><select name="method" required class="w-full rounded border-gray-300"><option value="cash">Cash</option><option value="transfer" @selected(old('method') === 'transfer')>Bank transfer</option></select></div>
        <div><label class="mb-1 block text-sm font-medium">Transfer reference</label><input name="reference" maxlength="120" value="{{ old('reference') }}" placeholder="Required for transfer" class="w-full rounded border-gray-300"></div>
        <div class="md:col-span-3"><button class="rounded bg-green-700 px-5 py-2 text-white">Record Payment & Generate Receipt</button></div>
    </form>
</div>
@endif

<div class="overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b p-5"><h4 class="font-semibold">Payment history and refund controls</h4><p class="text-sm text-gray-500">Refund requests require independent approval by a school administrator or principal.</p></div>
    <div class="divide-y">
        @forelse($invoice->payments as $payment)
            @php
                $reservedRefunds = (float) $payment->refundRequests->whereIn('status', ['pending', 'approved'])->sum('amount');
                $refundable = max(0, round((float) $payment->amount - $reservedRefunds, 2));
                $canReviewRefund = in_array(auth('school')->user()?->role, ['school_admin', 'principal'], true);
            @endphp
            <div class="p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <a class="font-semibold text-blue-700 hover:underline" href="{{ url('/school/' . request()->route('school') . '/fees/receipts/' . $payment->id) }}">{{ $payment->receipt_no }}</a>
                        <p class="mt-1 text-sm text-gray-500">{{ $payment->paid_at?->format('d M Y H:i') }} · <span class="capitalize">{{ $payment->method }}</span> · Ref: {{ $payment->reference ?: '—' }}</p>
                    </div>
                    <div class="text-right"><p class="text-lg font-bold">₦{{ number_format($payment->amount, 2) }}</p><p class="text-xs text-gray-500">Available for refund: ₦{{ number_format($refundable, 2) }}</p></div>
                </div>

                @if($payment->refundRequests->isNotEmpty())
                    <div class="mt-4 overflow-hidden rounded border">
                        <div class="border-b bg-gray-50 px-4 py-2 text-sm font-semibold">Refund requests</div>
                        @foreach($payment->refundRequests as $refund)
                            <div class="flex flex-col gap-3 border-b p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold">₦{{ number_format((float) $refund->amount, 2) }} · <span class="capitalize">{{ $refund->status }}</span></p>
                                    <p class="text-sm text-gray-600">{{ $refund->reason }}</p>
                                    <p class="text-xs text-gray-500">Requested {{ \Illuminate\Support\Carbon::parse($refund->created_at)->format('d M Y H:i') }} · {{ $refund->reference ?: 'No refund reference' }}</p>
                                </div>
                                @if($refund->status === 'pending' && $canReviewRefund)
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/fees/refunds/' . $refund->id . '/approve') }}">@csrf<button class="rounded bg-green-700 px-3 py-2 text-sm text-white" onclick="return confirm('Approve this refund and update the invoice balance?')">Approve</button></form>
                                        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/fees/refunds/' . $refund->id . '/reject') }}">@csrf<button class="rounded border border-red-300 px-3 py-2 text-sm text-red-700" onclick="return confirm('Reject this refund request?')">Reject</button></form>
                                    </div>
                                @elseif($refund->reviewed_at)
                                    <p class="text-xs text-gray-500">Reviewed {{ \Illuminate\Support\Carbon::parse($refund->reviewed_at)->format('d M Y H:i') }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($refundable > 0)
                    <details class="mt-4 rounded border p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-blue-700">Request a refund</summary>
                        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/fees/payments/' . $payment->id . '/refunds') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                            @csrf
                            <div><label class="mb-1 block text-sm font-medium">Refund amount (₦)</label><input type="number" name="amount" min="0.01" max="{{ number_format($refundable, 2, '.', '') }}" step="0.01" required class="w-full rounded border-gray-300"></div>
                            <div><label class="mb-1 block text-sm font-medium">Refund reference (optional)</label><input name="reference" maxlength="120" class="w-full rounded border-gray-300"></div>
                            <div class="md:col-span-2"><label class="mb-1 block text-sm font-medium">Reason</label><textarea name="reason" minlength="8" maxlength="1000" required rows="2" class="w-full rounded border-gray-300" placeholder="Explain why this payment should be refunded"></textarea></div>
                            <div class="md:col-span-2"><button class="rounded border border-blue-700 px-4 py-2 text-sm text-blue-700">Submit refund request for approval</button></div>
                        </form>
                    </details>
                @endif
            </div>
        @empty
            <p class="px-5 py-8 text-center text-gray-500">No payments recorded.</p>
        @endforelse
    </div>
</div>
@endsection
