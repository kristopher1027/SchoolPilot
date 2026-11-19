@extends('tenant.layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><a href="{{ url('/school/'.request()->route('school').'/payroll') }}" class="text-sm font-semibold text-blue-800 hover:underline">← Payroll management</a><h1 class="mt-2 text-2xl font-bold text-gray-900">{{ \Illuminate\Support\Carbon::createFromDate($payroll->year,$payroll->month,1)->format('F Y') }} Payroll</h1><p class="mt-1 text-sm text-gray-600">Status: <span class="font-semibold">{{ ucfirst($payroll->status) }}</span> · Currency: {{ $payroll->currency }}</p></div>
        @if($payroll->status==='draft' && $items->isEmpty())
            <form method="POST" action="{{ url('/school/'.request()->route('school').'/payroll/'.$payroll->id.'/generate') }}">@csrf<button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Generate payroll draft</button></form>
        @elseif($payroll->status==='draft' && $items->isNotEmpty())
            <form method="POST" action="{{ url('/school/'.request()->route('school').'/payroll/'.$payroll->id.'/approve') }}">@csrf<button class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white">Approve payroll</button></form>
        @endif
    </div>
    <div class="flex justify-end">
        <a href="{{ url('/school/'.request()->route('school').'/payroll/'.$payroll->id.'/export') }}"
           class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Download payroll register (CSV)
        </a>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([['Staff',$payroll->staff_count],['Gross pay',number_format($payroll->gross_total,2)],['Deductions',number_format($payroll->deductions_total,2)],['Net pay',number_format($payroll->net_total,2)]] as [$label,$value])
            <div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-xl font-bold text-gray-900">{{ $value }}</p><p class="text-xs text-gray-400">{{ $label==='Staff'?'members':$payroll->currency }}</p></div>
        @endforeach
    </div>

    @if($payroll->status==='approved')
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <h2 class="font-semibold text-gray-900">Record payroll payment</h2>
            <p class="mt-1 text-sm text-gray-600">Only use this after the school has actually paid the staff through its normal bank or finance process. This records the payment reference; it does not transfer money.</p>
            <form method="POST" action="{{ url('/school/'.request()->route('school').'/payroll/'.$payroll->id.'/paid') }}" class="mt-3 flex flex-wrap gap-2">@csrf<input required name="payment_reference" maxlength="150" placeholder="Bank batch / transfer reference" class="min-w-64 flex-1 rounded-lg border-gray-300 text-sm"><button class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Mark as paid</button></form>
        </section>
    @elseif($payroll->status==='paid')
        <p class="rounded-xl bg-green-50 p-4 text-sm text-green-800">Paid {{ $payroll->paid_at ? \Illuminate\Support\Carbon::parse($payroll->paid_at)->format('d M Y, H:i') : '' }} · Reference: {{ $payroll->payment_reference }}</p>
    @endif

    <section class="rounded-xl border {{ $reconciliation['passed'] ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }} p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold text-gray-900">Payroll reconciliation</h2>
                <p class="mt-1 text-sm text-gray-600">Compares the saved period summary with its staff payroll items and checks required payment metadata.</p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $reconciliation['passed'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $reconciliation['passed'] ? 'All checks matched' : 'Review required' }}</span>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-gray-500"><tr><th class="py-2">Check</th><th class="py-2">Saved value</th><th class="py-2">Reconciled value</th><th class="py-2">Result</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($reconciliation['checks'] as $check)
                        <tr>
                            <td class="py-2 font-medium text-gray-800">{{ $check['label'] }}</td>
                            <td class="py-2">{{ is_numeric($check['expected']) ? number_format((float)$check['expected'], 2) : $check['expected'] }}</td>
                            <td class="py-2">{{ is_numeric($check['actual']) ? number_format((float)$check['actual'], 2) : $check['actual'] }}</td>
                            <td class="py-2"><span class="font-semibold {{ $check['passed'] ? 'text-green-700' : 'text-red-700' }}">{{ $check['passed'] ? 'Matched' : 'Mismatch' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($payroll->status==='paid')
            <p class="mt-3 text-xs text-gray-600">This checks that a payment reference is recorded. It does not verify a bank transfer or confirm that funds reached staff.</p>
        @endif
    </section>

    <section class="overflow-hidden rounded-xl bg-white shadow">
        <div class="border-b p-4"><h2 class="font-semibold text-gray-900">Payroll items</h2><p class="mt-1 text-xs text-gray-500">Amounts are snapshots from salary assignments at the time the draft was generated.</p></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Staff</th><th class="px-4 py-3">Base</th><th class="px-4 py-3">Allowances</th><th class="px-4 py-3">Deductions</th><th class="px-4 py-3">Gross</th><th class="px-4 py-3">Net</th></tr></thead>
            <tbody class="divide-y">
                @forelse($items as $item)<tr><td class="px-4 py-3"><p class="font-medium text-gray-900">{{ $item->staff_name }}</p><p class="text-xs text-gray-500">{{ $item->staff_no }}</p></td><td class="px-4 py-3">{{ number_format($item->base_salary,2) }}</td><td class="px-4 py-3">{{ number_format($item->allowances_total,2) }}</td><td class="px-4 py-3">{{ number_format($item->deductions_total,2) }}</td><td class="px-4 py-3">{{ number_format($item->gross_pay,2) }}</td><td class="px-4 py-3 font-semibold">{{ number_format($item->net_pay,2) }}</td></tr>
                @empty<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No payroll items generated. Check salary assignments, period dates and currency, then generate the draft.</td></tr>@endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
