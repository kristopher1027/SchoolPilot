@extends('tenant.layouts.app')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Students Directory</h3>
            <p class="text-sm text-gray-500">Search and manage enrolled students.</p>
        </div>
        <a href="{{ url('/school/' . request()->route('school') . '/students/create') }}" class="inline-flex justify-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow">Enroll Student</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="status">{{ session('success') }}</div>
    @endif

    <form method="GET" class="bg-white rounded-lg shadow p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Name or admission number" class="rounded-md border-gray-300">
        <select name="section_id" class="rounded-md border-gray-300">
            <option value="">All sections</option>
            @foreach($sections as $section)
                <option value="{{ $section->id }}" @selected((string) request('section_id') === (string) $section->id)>{{ $section->name }}</option>
            @endforeach
        </select>
        <select name="class_id" class="rounded-md border-gray-300">
            <option value="">All classes</option>
            @foreach($classes as $class)
                <option value="{{ $class->id }}" @selected((string) request('class_id') === (string) $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-md border-gray-300">
            <option value="">All statuses</option>
            @foreach(['active' => 'Active', 'graduated' => 'Graduated', 'transferred' => 'Transferred', 'withdrawn' => 'Withdrawn'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="flex-1 bg-slate-800 text-white rounded-md px-4 py-2">Filter</button>
            <a href="{{ url('/school/' . request()->route('school') . '/students') }}" class="px-3 py-2 border rounded-md">Reset</a>
        </div>
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Admission No.</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Section / Class</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Gender</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($students as $student)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $student->admission_no }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $student->section?->name ?? '—' }} / {{ $student->schoolClass?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 capitalize">{{ $student->gender }}</td>
                            <td class="px-4 py-3 text-sm"><span class="inline-flex rounded-full px-2 py-1 text-xs bg-gray-100 text-gray-700">{{ ucfirst($student->status) }}</span></td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <a class="text-blue-600 hover:underline" href="{{ url('/school/' . request()->route('school') . '/students/' . $student->id) }}">View</a>
                                <span class="text-gray-300 mx-1">|</span>
                                <a class="text-blue-600 hover:underline" href="{{ url('/school/' . request()->route('school') . '/students/' . $student->id . '/edit') }}">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">No students match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t">{{ $students->links() }}</div>
    </div>
@endsection
