@extends('tenant.layouts.app')

@section('content')
<div class="mb-6">
    <a href="{{ url('/school/' . request()->route('school') . '/parents') }}" class="text-sm text-blue-600 hover:underline">← Parents & Guardians</a>
    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">{{ $guardian->user?->name ?? 'Guardian profile' }}</h3>
            <p class="text-sm text-gray-500">{{ $guardian->relationship ?: 'Parent / Guardian' }} · {{ $guardian->students->count() }} linked {{ \Illuminate\Support\Str::plural('student', $guardian->students->count()) }}</p>
        </div>
        <a href="{{ url('/school/' . request()->route('school') . '/parents/' . $guardian->id . '/edit') }}" class="rounded bg-blue-600 px-4 py-2 text-center text-white">Edit Guardian</a>
    </div>
</div>

@if(session('success'))
<div class="mb-5 rounded border-l-4 border-green-500 bg-green-50 p-4 text-green-800" role="status">{{ session('success') }}</div>
@endif

<div class="mb-5 grid grid-cols-1 gap-5 md:grid-cols-2">
    <section class="rounded-lg bg-white p-5 shadow">
        <h4 class="mb-4 font-semibold text-gray-800">Contact Information</h4>
        <dl class="space-y-3 text-sm">
            <div><dt class="text-gray-500">Full name</dt><dd class="font-medium">{{ $guardian->user?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ $guardian->user?->email ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ $guardian->user?->phone ?: '—' }}</dd></div>
            <div><dt class="text-gray-500">Occupation</dt><dd class="font-medium">{{ $guardian->occupation ?: '—' }}</dd></div>
            <div><dt class="text-gray-500">Relationship</dt><dd class="font-medium">{{ $guardian->relationship ?: '—' }}</dd></div>
            <div><dt class="text-gray-500">Address</dt><dd class="whitespace-pre-line font-medium">{{ $guardian->address ?: '—' }}</dd></div>
        </dl>
    </section>
    <section class="rounded-lg bg-white p-5 shadow">
        <h4 class="mb-4 font-semibold text-gray-800">Linked Students</h4>
        <p class="mb-3 text-sm text-gray-500">Students connected to this parent or guardian.</p>
        <div class="text-3xl font-bold text-gray-800">{{ $guardian->students->count() }}</div>
        <p class="text-sm text-gray-500">Linked student records</p>
    </section>
</div>

<section class="overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b p-5">
        <h4 class="font-semibold text-gray-800">Children / Students</h4>
        <p class="text-sm text-gray-500">Open a student record to review academic placement and guardian links.</p>
    </div>
    @if($guardian->students->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Student</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Admission number</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Section / Class</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
            @foreach($guardian->students as $student)
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $student->admission_no }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $student->section?->name ?? '—' }} / {{ $student->schoolClass?->name ?? 'Unassigned' }}</td>
                    <td class="px-4 py-3 text-sm"><span class="rounded bg-gray-100 px-2 py-1 text-xs capitalize text-gray-700">{{ $student->status }}</span></td>
                    <td class="px-4 py-3 text-right"><a href="{{ url('/school/' . request()->route('school') . '/students/' . $student->id) }}" class="text-sm text-blue-600 hover:underline">View student</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="p-8 text-center">
        <h5 class="font-semibold text-gray-800">No students linked yet</h5>
        <p class="mt-1 text-sm text-gray-500">Open a student profile to link this guardian to the correct student.</p>
        <a href="{{ url('/school/' . request()->route('school') . '/students') }}" class="mt-4 inline-block text-sm font-medium text-blue-600 hover:underline">Browse students</a>
    </div>
    @endif
</section>
@endsection
