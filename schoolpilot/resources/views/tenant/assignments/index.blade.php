@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Teacher Assignments</h3>
        <p class="text-gray-600">Assign teachers to classes and subjects.</p>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Assignment Form -->
        <div class="bg-white rounded-lg shadow p-6 lg:col-span-1 h-fit">
            <h4 class="text-lg font-bold text-gray-800 mb-4">New Assignment</h4>
            <form method="POST" action="/school/greenfield/assignments" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700">Teacher</label>
                    <select name="staff_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select Teacher</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->user->name }} ({{ ucwords(str_replace('_', ' ', $teacher->user->role)) }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Class</label>
                    <select name="class_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Subject</label>
                    <select name="subject_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Session</label>
                    <select name="session_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Term</label>
                    <select name="term_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        @foreach($terms as $term)
                            <option value="{{ $term->id }}">{{ $term->name }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded shadow hover:bg-blue-700">Assign Teacher</button>
            </form>
        </div>

        <!-- Existing Assignments List -->
        <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
            <h4 class="text-lg font-bold text-gray-800 mb-4">Current Assignments</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($assignments as $assign)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">{{ $assign->staff->user->name ?? 'Unknown' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ $assign->schoolClass->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ $assign->subject->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-center text-gray-500">No assignments yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection