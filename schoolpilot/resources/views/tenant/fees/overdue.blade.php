@extends('tenant.layouts.app')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h3 class="text-2xl font-bold text-gray-800">Overdue Invoices</h3>
        <p class="text-sm text-gray-500">Invoices past their due date with an outstanding balance.</p>
    </div>
    <a class="rounded border px-4 py-2" href="{{ url('/school/' . request()->route('school') . '/fees') }}">Back to Fees</a>
</div>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Overdue invoices</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">{{ number_format($summary['count']) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Outstanding overdue balance</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">₦{{ number_format($summary['balance'], 2) }}</p>
    </div>
</div>

<div class="overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b p-5">
        <h4 class="font-semibold text-gray-800">Overdue student accounts</h4>
        <p class="text-sm text-gray-500">Daily reminders are sent to each student's primary guardian when a valid email address is available.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Student</th>
                    <th class="px-4 py-3">Session / Term</th>
                    <th class="px-4 py-3">Due date</th>
                    <th class="px-4 py-3">Days overdue</th>
                    <th class="px-4 py-3">Outstanding</th>
                    <th class="px-4 py-3">Primary guardian</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($invoices as $invoice)
                    @php($guardian = $invoice->student?->guardians?->first())
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium">{{ $invoice->student?->last_name }}, {{ $invoice->student?->first_name }}<div class="text-xs text-gray-500">{{ $invoice->student?->admission_no }}</div></td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->academicSession?->name }}<div class="text-xs text-gray-500">{{ $invoice->term?->name }}</div></td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $invoice->due_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->due_date?->diffInDays(today()) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold">₦{{ number_format((float) $invoice->balance, 2) }}</td>
                        <td class="px-4 py-3 text-sm">{{ $guardian?->user?->name ?? 'Not linked' }}<div class="text-xs text-gray-500">{{ $guardian?->user?->email ?? 'No primary guardian email' }}</div></td>
                        <td class="px-4 py-3 text-sm"><a class="text-blue-700 hover:underline" href="{{ url('/school/' . request()->route('school') . '/fees/invoices/' . $invoice->id) }}">Open invoice</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">There are no overdue invoices with an outstanding balance.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t p-4">{{ $invoices->links() }}</div>
</div>
@endsection
