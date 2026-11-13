@extends('tenant.layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-gray-900">Staff Attendance</h1><p class="mt-1 text-sm text-gray-600">Record and review the daily attendance register for active staff.</p></div>
        <a href="{{ url('/school/'.request()->route('school').'/hr/leave') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Leave Management</a>
    </div>
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <label class="text-sm font-medium text-gray-700">Attendance date<input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
        <button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Load register</button>
    </form>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach([['Present',$summary['present']],['Absent',$summary['absent']],['Late',$summary['late']],['On leave',$summary['on_leave']],['Unmarked',$summary['unmarked']]] as [$label,$value])
            <div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($value) }}</p></div>
        @endforeach
    </div>
    <form method="POST" action="{{ url('/school/'.request()->route('school').'/hr/staff-attendance') }}" class="overflow-hidden rounded-xl bg-white shadow">
        @csrf
        <input type="hidden" name="attendance_date" value="{{ $date }}">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b p-4"><div><h2 class="font-semibold text-gray-900">Daily staff register</h2><p class="text-xs text-gray-500">All active staff must have a status before saving.</p></div><button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Save attendance</button></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Staff</th><th class="px-4 py-3">Staff no.</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Check in</th><th class="px-4 py-3">Check out</th><th class="px-4 py-3">Notes</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($staff as $member)
                        @php($record = $records->get($member->id))
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ $member->user->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $member->staff_no }}</td>
                            <td class="px-4 py-3"><select required name="statuses[{{ $member->id }}]" class="rounded-lg border-gray-300 text-sm"><option value="">Select</option>@foreach(['present'=>'Present','absent'=>'Absent','late'=>'Late','on_leave'=>'On leave'] as $value=>$label)<option value="{{ $value }}" @selected(old('statuses.'.$member->id, $record->status ?? '')===$value)>{{ $label }}</option>@endforeach</select></td>
                            <td class="px-4 py-3"><input type="time" name="check_in[{{ $member->id }}]" value="{{ old('check_in.'.$member->id, $record->check_in ?? '') }}" class="w-28 rounded-lg border-gray-300 text-sm"></td>
                            <td class="px-4 py-3"><input type="time" name="check_out[{{ $member->id }}]" value="{{ old('check_out.'.$member->id, $record->check_out ?? '') }}" class="w-28 rounded-lg border-gray-300 text-sm"></td>
                            <td class="px-4 py-3"><input type="text" name="notes[{{ $member->id }}]" value="{{ old('notes.'.$member->id, $record->notes ?? '') }}" maxlength="1000" class="min-w-36 rounded-lg border-gray-300 text-sm" placeholder="Optional note"></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No active staff profiles found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t p-4 text-right"><button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Save attendance</button></div>
    </form>
</div>
@endsection
