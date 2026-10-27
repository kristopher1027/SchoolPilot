@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Teacher Portal</h3>
        <p class="text-gray-600">Welcome back, {{ Auth::user()->name }}.</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h4 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">My Assignments</h4>
        @if($assignments->isEmpty())
            <p class="text-gray-500">You have not been assigned to any classes or subjects yet. Please contact the Admin.</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($assignments as $assign)
                    <div class="p-4 border rounded-lg flex justify-between items-center bg-gray-50">
                        <div>
                            <p class="font-bold text-gray-800">{{ $assign->subject->name }}</p>
                            <p class="text-sm text-gray-500">{{ $assign->schoolClass->name }}</p>
                        </div>
                        <a href="/school/greenfield/scores?class_id={{ $assign->class_id }}&subject_id={{ $assign->subject_id }}&term_id={{ $assign->term_id }}&session_id={{ $assign->academic_session_id }}" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 text-sm rounded">Enter Scores</a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection