@extends('tenant.layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div><h1 class="text-2xl font-bold text-gray-900">My Payslips</h1><p class="mt-1 text-sm text-gray-600">View approved payroll statements for your staff account.</p></div>
    <div class="overflow-hidden rounded-xl bg-white shadow"><div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Period</th><th class="px-4 py-3">Net pay</th><th class="px-4 py-3">Payroll status</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y">
            @forelse($items as $item)<tr><td class="px-4 py-3 font-medium">{{ \Illuminate\Support\Carbon::createFromDate($item->year,$item->month,1)->format('F Y') }}</td><td class="px-4 py-3">{{ $item->currency }} {{ number_format($item->net_pay,2) }}</td><td class="px-4 py-3">{{ ucfirst($item->payroll_status) }}</td><td class="px-4 py-3"><a class="font-semibold text-blue-800 hover:underline" href="{{ url('/school/'.request()->route('school').'/payroll/payslips/'.$item->id) }}">View payslip</a></td></tr>
            @empty<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No approved payslips are available yet.</td></tr>@endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
