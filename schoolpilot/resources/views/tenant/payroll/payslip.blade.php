@extends('tenant.layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div class="flex items-center justify-between"><a href="{{ url('/school/'.request()->route('school').'/payroll/payslips') }}" class="text-sm font-semibold text-blue-800 hover:underline">← My payslips</a><button type="button" onclick="window.print()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 print:hidden">Print payslip</button></div>
    <article class="rounded-xl bg-white p-6 shadow print:shadow-none">
        <div class="border-b pb-5 text-center"><h1 class="text-2xl font-bold text-gray-900">Salary Payslip</h1><p class="mt-1 text-sm text-gray-500">{{ \Illuminate\Support\Carbon::createFromDate($payslip->year,$payslip->month,1)->format('F Y') }}</p><p class="mt-1 text-xs text-gray-500">{{ ucfirst($payslip->payroll_status) }} payroll</p></div>
        <div class="grid grid-cols-2 gap-4 border-b py-5 text-sm"><div><p class="text-xs text-gray-500">Staff name</p><p class="font-semibold text-gray-900">{{ $payslip->staff_name }}</p></div><div><p class="text-xs text-gray-500">Staff number</p><p class="font-semibold text-gray-900">{{ $payslip->staff_no }}</p></div></div>
        @php($breakdown = json_decode($payslip->breakdown, true) ?: [])
        <div class="space-y-3 py-5"><h2 class="font-semibold text-gray-900">Earnings</h2><div class="flex justify-between text-sm"><span>Basic salary</span><span>{{ $payslip->currency }} {{ number_format($payslip->base_salary,2) }}</span></div>
            @foreach($breakdown['allowances'] ?? [] as $allowance)<div class="flex justify-between text-sm"><span>{{ $allowance['name'] }}</span><span>{{ $payslip->currency }} {{ number_format($allowance['amount'],2) }}</span></div>@endforeach
            <div class="flex justify-between border-t pt-3 text-sm font-semibold"><span>Gross pay</span><span>{{ $payslip->currency }} {{ number_format($payslip->gross_pay,2) }}</span></div>
        </div>
        <div class="space-y-3 border-t py-5"><h2 class="font-semibold text-gray-900">Deductions</h2>
            @forelse($breakdown['deductions'] ?? [] as $deduction)<div class="flex justify-between text-sm"><span>{{ $deduction['name'] }}</span><span>{{ $payslip->currency }} {{ number_format($deduction['amount'],2) }}</span></div>@empty<p class="text-sm text-gray-500">No deductions recorded.</p>@endforelse
            <div class="flex justify-between border-t pt-3 text-sm font-semibold"><span>Total deductions</span><span>{{ $payslip->currency }} {{ number_format($payslip->deductions_total,2) }}</span></div>
        </div>
        <div class="rounded-lg bg-blue-50 p-4"><div class="flex justify-between text-lg font-bold text-gray-900"><span>Net pay</span><span>{{ $payslip->currency }} {{ number_format($payslip->net_pay,2) }}</span></div></div>
        @if($payslip->payroll_status==='paid')<p class="mt-4 text-xs text-gray-500">Payment recorded: {{ $payslip->paid_at ? \Illuminate\Support\Carbon::parse($payslip->paid_at)->format('d M Y') : '—' }} · Reference: {{ $payslip->payment_reference ?: '—' }}</p>@endif
        <p class="mt-6 border-t pt-4 text-center text-xs text-gray-400">This is a system-generated payroll statement.</p>
    </article>
</div>
@endsection
