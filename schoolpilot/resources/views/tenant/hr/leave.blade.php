@extends('tenant.layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-gray-900">Staff Leave Management</h1><p class="mt-1 text-sm text-gray-600">Apply for leave, track available allowance and review applications.</p></div>
        @if($isManager)<a href="{{ url('/school/'.request()->route('school').'/hr/staff-attendance') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Staff Attendance</a>@endif
    </div>

    @if($staffProfile && $balances->isNotEmpty())
        <section><h2 class="mb-3 text-lg font-semibold text-gray-900">My leave balance — {{ now()->year }}</h2><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($balances as $balance)
                <div class="rounded-xl bg-white p-4 shadow"><p class="font-semibold text-gray-900">{{ $balance->name }}</p><p class="mt-2 text-sm text-gray-500">{{ $balance->is_paid ? 'Paid leave' : 'Unpaid leave' }}</p><div class="mt-3 flex items-end justify-between"><div><p class="text-xs text-gray-500">Remaining</p><p class="text-2xl font-bold text-gray-900">{{ $balance->remaining }} <span class="text-sm font-normal text-gray-500">days</span></p></div><p class="text-xs text-gray-500">{{ $balance->used }} used / {{ $balance->entitled }} entitled</p></div></div>
            @endforeach
        </div></section>
    @endif

    @if($staffProfile)
        <section class="rounded-xl bg-white p-5 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Apply for leave</h2>
            <p class="mt-1 text-sm text-gray-500">Working days exclude Saturdays and Sundays. Requests crossing calendar years must be submitted separately.</p>
            <form method="POST" action="{{ url('/school/'.request()->route('school').'/hr/leave') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <label class="text-sm font-medium text-gray-700 sm:col-span-2">Leave type<select required name="leave_type_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"><option value="">Choose leave type</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected(old('leave_type_id')==$type->id)>{{ $type->name }} — {{ $type->is_paid ? $type->annual_days.' paid days/year' : 'unpaid' }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-gray-700">Start date<input required type="date" name="start_date" min="{{ now()->toDateString() }}" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
                <label class="text-sm font-medium text-gray-700">End date<input required type="date" name="end_date" min="{{ now()->toDateString() }}" value="{{ old('end_date') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
                <label class="text-sm font-medium text-gray-700 sm:col-span-2">Reason<textarea required name="reason" rows="3" maxlength="3000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" placeholder="Explain the reason for your leave">{{ old('reason') }}</textarea></label>
                <div class="sm:col-span-2"><button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Submit leave application</button></div>
            </form>
        </section>
    @endif

    @if($isManager)
        <section class="rounded-xl bg-white p-5 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Configure leave type</h2>
            <form method="POST" action="{{ url('/school/'.request()->route('school').'/hr/leave-types') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <label class="text-sm font-medium text-gray-700">Leave name<input required name="name" maxlength="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" placeholder="Annual leave"></label>
                <label class="text-sm font-medium text-gray-700">Annual days<input required type="number" min="0" max="365" name="annual_days" value="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
                <label class="flex items-center gap-2 pt-6 text-sm text-gray-700"><input type="checkbox" name="is_paid" value="1" checked> Paid leave</label>
                <label class="flex items-center gap-2 pt-6 text-sm text-gray-700"><input type="checkbox" name="requires_approval" value="1" checked> Requires approval</label>
                <div class="sm:col-span-2 lg:col-span-4"><button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Add leave type</button></div>
            </form>
            <p class="mt-2 text-xs text-gray-500">Leave types are tenant-specific. Unpaid leave is not deducted from the paid leave allowance.</p>
        </section>
    @endif

    <section class="overflow-hidden rounded-xl bg-white shadow">
        <div class="border-b p-4"><h2 class="font-semibold text-gray-900">{{ $isManager ? 'Staff leave applications' : 'My leave applications' }}</h2><p class="mt-1 text-xs text-gray-500">Latest 200 applications.</p></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>@if($isManager)<th class="px-4 py-3">Staff</th>@endif<th class="px-4 py-3">Leave type</th><th class="px-4 py-3">Period</th><th class="px-4 py-3">Days</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Review</th>@if($isManager)<th class="px-4 py-3">Action</th>@endif</tr></thead>
            <tbody class="divide-y">
                @forelse($requests as $application)
                    <tr class="align-top">
                        @if($isManager)<td class="whitespace-nowrap px-4 py-3"><p class="font-medium text-gray-900">{{ $application->staff_name }}</p><p class="text-xs text-gray-500">{{ $application->staff_no }}</p></td>@endif
                        <td class="whitespace-nowrap px-4 py-3">{{ $application->leave_type_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ \Illuminate\Support\Carbon::parse($application->start_date)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($application->end_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $application->days_requested }}</td>
                        <td class="min-w-48 px-4 py-3">{{ $application->reason }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $application->status==='approved'?'bg-green-100 text-green-800':($application->status==='rejected'?'bg-red-100 text-red-800':'bg-amber-100 text-amber-800') }}">{{ ucfirst($application->status) }}</span></td>
                        <td class="min-w-40 px-4 py-3 text-xs text-gray-600">{{ $application->review_note ?: '—' }}</td>
                        @if($isManager)<td class="min-w-56 px-4 py-3">@if($application->status==='pending')
                            <form method="POST" action="{{ url('/school/'.request()->route('school').'/hr/leave/'.$application->id.'/review') }}" class="space-y-2">@csrf<input name="review_note" maxlength="2000" placeholder="Optional review note" class="w-full rounded-lg border-gray-300 text-xs"><div class="flex gap-2"><button name="decision" value="approved" class="rounded-lg bg-green-700 px-3 py-1.5 text-xs font-semibold text-white">Approve</button><button name="decision" value="rejected" class="rounded-lg bg-red-700 px-3 py-1.5 text-xs font-semibold text-white">Reject</button></div></form>
                        @else<span class="text-xs text-gray-400">Reviewed</span>@endif</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $isManager ? 8 : 6 }}" class="px-4 py-8 text-center text-gray-500">No leave applications found.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
