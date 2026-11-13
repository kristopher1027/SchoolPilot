@extends('tenant.layouts.app')
@section('content')
<div class="mb-6">
    <h3 class="text-2xl font-bold text-gray-800">Parent Portal</h3>
    <p class="text-sm text-gray-500">A read-only view of your linked children's school information.</p>
</div>

@if($guardian->students->isEmpty())
    <div class="rounded-lg bg-white p-8 text-center shadow">
        <h4 class="font-semibold text-gray-800">No students linked yet</h4>
        <p class="mt-2 text-sm text-gray-500">Please contact the school office to link your parent account to a student profile.</p>
    </div>
@else
    <div class="mb-6 rounded-lg bg-white p-5 shadow">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h4 class="text-lg font-semibold text-gray-800">School announcements</h4>
                <p class="text-sm text-gray-500">Updates published for parents and the whole school.</p>
            </div>
            <span class="text-xs text-gray-500">{{ $announcements->count() }} recent</span>
        </div>
        @forelse($announcements as $announcement)
            <article class="border-t py-4 first:border-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <h5 class="font-semibold text-gray-800">{{ $announcement->title }}</h5>
                            @if(isset($announcementReads[$announcement->id]))
                                <span class="rounded-full bg-green-50 px-2 py-1 text-xs text-green-700">Read</span>
                            @else
                                <span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700">New</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500">
                            {{ $announcement->published_at?->format('d M Y, h:i A') }}
                            @if($announcement->author) · {{ $announcement->author->name }} @endif
                        </p>
                        <div class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $announcement->body }}</div>
                    </div>
                    @if(! isset($announcementReads[$announcement->id]))
                        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/parent-portal/announcements/' . $announcement->id . '/read') }}">
                            @csrf
                            <button class="rounded border border-blue-200 px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50">Mark as read</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-sm text-gray-500">There are no current announcements for parents.</p>
        @endforelse
    </div>
    <div class="space-y-6">
        @foreach($guardian->students as $student)
            <section class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-800">{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</h4>
                            <p class="text-sm text-gray-500">{{ $student->admission_no }} · {{ $student->section?->name }} · {{ $student->schoolClass?->name }}</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium capitalize text-blue-700">{{ $student->status }}</span>
                    </div>
                </div>

                @php
                    $studentInvoices = $invoices->get($student->id, collect());
                    $studentInvoiceIds = $studentInvoices->pluck('id');
                    $outstanding = (float) $studentInvoices->sum('balance');
                    $invoiced = (float) $studentInvoices->sum('total_amount');
                    $paid = (float) $studentInvoices->sum('amount_paid');
                @endphp
                <div class="border-b bg-gray-50 p-5">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h5 class="font-semibold text-gray-800">Fee statement</h5>
                        <span class="text-xs text-gray-500">Read-only · Contact the school for corrections or payment arrangements</span>
                    </div>
                    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded bg-white p-3 shadow-sm"><p class="text-xs text-gray-500">Total invoiced</p><p class="text-lg font-bold">₦{{ number_format($invoiced, 2) }}</p></div>
                        <div class="rounded bg-white p-3 shadow-sm"><p class="text-xs text-gray-500">Recorded as paid</p><p class="text-lg font-bold text-green-700">₦{{ number_format($paid, 2) }}</p></div>
                        <div class="rounded bg-white p-3 shadow-sm"><p class="text-xs text-gray-500">Outstanding balance</p><p class="text-lg font-bold {{ $outstanding > 0 ? 'text-amber-700' : 'text-green-700' }}">₦{{ number_format($outstanding, 2) }}</p></div>
                    </div>
                    @if($studentInvoices->isEmpty())
                        <p class="text-sm text-gray-500">No fee invoices are available.</p>
                    @else
                        <div class="overflow-x-auto rounded border bg-white">
                            <table class="min-w-full divide-y">
                                <thead class="bg-gray-100 text-left text-xs uppercase text-gray-500">
                                    <tr><th class="px-3 py-2">Session / term</th><th class="px-3 py-2">Due date</th><th class="px-3 py-2">Invoiced</th><th class="px-3 py-2">Paid</th><th class="px-3 py-2">Balance</th><th class="px-3 py-2">Status</th></tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($studentInvoices as $invoice)
                                        <tr>
                                            <td class="px-3 py-3 text-sm"><p class="font-medium">{{ $invoice->session_name }}</p><p class="text-xs text-gray-500">{{ $invoice->term_name }}</p></td>
                                            <td class="whitespace-nowrap px-3 py-3 text-sm">{{ $invoice->due_date ? \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d M Y') : '—' }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-sm">₦{{ number_format((float) $invoice->total_amount, 2) }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-sm text-green-700">₦{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-sm font-semibold">₦{{ number_format((float) $invoice->balance, 2) }}</td>
                                            <td class="px-3 py-3 text-sm capitalize">{{ $invoice->status }}</td>
                                        </tr>
                                        @if($payments->get($invoice->id, collect())->isNotEmpty())
                                            <tr><td colspan="6" class="bg-gray-50 px-3 py-2">
                                                <p class="mb-1 text-xs font-semibold text-gray-600">Recorded payments</p>
                                                <div class="space-y-1">
                                                    @foreach($payments->get($invoice->id, collect()) as $payment)
                                                        <div class="flex flex-wrap justify-between gap-2 text-xs text-gray-600">
                                                            <span>{{ $payment->paid_at ? \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d M Y') : 'Date unavailable' }} · {{ ucfirst($payment->method) }} · Receipt {{ $payment->receipt_no }}</span>
                                                            <span class="font-medium">₦{{ number_format((float) $payment->amount, 2) }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td></tr>
                                        @endif
                                        @if($refunds->get($invoice->id, collect())->isNotEmpty())
                                            <tr><td colspan="6" class="bg-amber-50 px-3 py-2">
                                                <p class="mb-1 text-xs font-semibold text-amber-800">Approved refunds</p>
                                                @foreach($refunds->get($invoice->id, collect()) as $refund)
                                                    <div class="flex flex-wrap justify-between gap-2 text-xs text-amber-800">
                                                        <span>{{ ucfirst($refund->status) }}{{ $refund->settled_at ? ' · Settled ' . \Illuminate\Support\Carbon::parse($refund->settled_at)->format('d M Y') : ' · Awaiting settlement' }}</span>
                                                        <span>₦{{ number_format((float) $refund->amount, 2) }}</span>
                                                    </div>
                                                @endforeach
                                            </td></tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-5 p-5 lg:grid-cols-2">
                    <div>
                        <h5 class="mb-3 font-semibold text-gray-800">Recent attendance</h5>
                        @php($studentAttendance = $attendance->get($student->id, collect()))
                        @if($studentAttendance->isEmpty())
                            <p class="text-sm text-gray-500">No attendance records available yet.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($studentAttendance as $record)
                                    <div class="flex items-center justify-between rounded border p-3">
                                        <span class="text-sm">{{ $record->date?->format('d M Y') }}</span>
                                        <span class="text-sm font-medium capitalize
                                            {{ $record->status === 'present' ? 'text-green-700' : ($record->status === 'absent' ? 'text-red-700' : 'text-amber-700') }}">{{ $record->status }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <h5 class="mb-3 font-semibold text-gray-800">Published results</h5>
                        @php($studentResults = $publishedResults->get($student->id, collect()))
                        @if($studentResults->isEmpty())
                            <p class="text-sm text-gray-500">No published results available yet.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($studentResults as $result)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded border p-3">
                                        <div>
                                            <p class="text-sm font-medium">{{ $result->session_name }}</p>
                                            <p class="text-xs text-gray-500">{{ $result->term_name }}</p>
                                        </div>
                                        <a class="text-sm font-medium text-blue-700 hover:underline" href="{{ url('/school/' . request()->route('school') . '/parent-portal/results/' . $student->id . '?term_id=' . $result->term_id . '&session_id=' . $result->academic_session_id) }}">View report card</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach
    </div>
@endif
@endsection
