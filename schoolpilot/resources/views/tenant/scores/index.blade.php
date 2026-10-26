@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Score Entry</h3>
        <p class="text-gray-600">Select a class and subject to enter scores.</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
       <form method="GET" action="/school/greenfield/scores/entry">
    <!-- Remove the @csrf line -->
    <div class="space-y-4">
        <!-- rest of form stays the same -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Academic Session</label>
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
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded shadow hover:bg-blue-700">Load Students for Score Entry</button>
            </div>
        </form>
    </div>
@endsection