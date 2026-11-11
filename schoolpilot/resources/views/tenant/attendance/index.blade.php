@extends('tenant.layouts.app')

@section('content')
<div class="mb-6">
    <h3 class="text-2xl font-bold text-gray-800">Attendance Management</h3>
    <p class="text-gray-600">Record and review daily student attendance by class.</p>
</div>

@if(session('success'))
    <div class="mb-4 rounded border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded border border-red-200 bg-red-50 p-4 text-red-800">
        <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="mb-6 rounded-lg bg-white p-5 shadow">
    <form method="GET" action="{{ url('/school/' . request()->route('school') . '/attendance') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label for="class_id" class="mb-1 block text-sm font-medium text-gray-700">Class</label>
            <select id="class_id" name="class_id" required class="w-full rounded border-gray-300">
                <option value="">Select class</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected((int) $selectedClassId === $class->id)>
                        {{ $class->name }}{{ $class->section ? ' — ' . $class->section->name : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="date" class="mb-1 block text-sm font-medium text-gray-700">Attendance date</label>
            <input id="date" type="date" name="date" value="{{ $date }}" required class="w-full rounded border-gray-300">
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full rounded bg-blue-700 px-4 py-2 font-semibold text-white hover:bg-blue-800">Load Register</button>
        </div>
    </form>
</div>

@if($classes->isEmpty())
    <div class="rounded-lg bg-white p-6 text-gray-600 shadow">No classes are available for your account. Ask the school administrator to assign a class.</div>
@elseif($selectedClass)
    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $key => $label)
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-bold text-gray-800">{{ $summary[$key] }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ url('/school/' . request()->route('school') . '/attendance') }}" class="overflow-hidden rounded-lg bg-white shadow">
        @csrf
        <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">
        <input type="hidden" name="date" value="{{ $date }}">
        <div class="flex flex-col gap-2 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h4 class="font-bold text-gray-800">{{ $selectedClass->name }} Attendance Register</h4>
                <p class="text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($date)->format('l, d F Y') }} · {{ $students->count() }} active students</p>
            </div>
            <button type="submit" class="rounded bg-green-700 px-5 py-2 font-semibold text-white hover:bg-green-800" @disabled($students->isEmpty())>Save Attendance</button>
        </div>

        @if($students->isEmpty())
            <p class="p-6 text-gray-600">There are no active students assigned to this class.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Admission No.</th>
                            <th class="px-4 py-3">Attendance Status</th>
                            <th class="px-4 py-3">Note (optional)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($students as $student)
                            @php($record = $records->get($student->id))
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-800">{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $student->admission_no }}</td>
                                <td class="px-4 py-3">
                                    <select name="statuses[{{ $student->id }}]" required class="w-full min-w-32 rounded border-gray-300 text-sm">
                                        @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('statuses.' . $student->id, $record?->status ?? 'present') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="notes[{{ $student->id }}]" maxlength="1000" value="{{ old('notes.' . $student->id, $record?->notes) }}" placeholder="Optional note" class="w-full min-w-40 rounded border-gray-300 text-sm">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end border-t p-5">
                <button type="submit" class="rounded bg-green-700 px-5 py-2 font-semibold text-white hover:bg-green-800">Save Attendance</button>
            </div>
        @endif
    </form>
@endif
@endsection
