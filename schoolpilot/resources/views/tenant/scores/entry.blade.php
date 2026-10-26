@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Enter Scores</h3>
            <p class="text-gray-600">Class: {{ \App\Models\Tenant\SchoolClass::find($classId)->name }} | Subject: {{ \App\Models\Tenant\Subject::find($subjectId)->name }}</p>
        </div>
        <a href="/school/greenfield/scores" class="text-blue-600 hover:underline">&larr; Back</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if($students->isEmpty())
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
            No students found in this class. Please add students first.
        </div>
    @else
        <form method="POST" action="/school/greenfield/scores/store">
            @csrf
            <input type="hidden" name="class_id" value="{{ $classId }}">
            <input type="hidden" name="subject_id" value="{{ $subjectId }}">
            <input type="hidden" name="term_id" value="{{ $termId }}">
            <input type="hidden" name="session_id" value="{{ $sessionId }}">

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                            @foreach($components as $component)
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    {{ $component->name }} <br><span class="text-gray-400">(Max: {{ $component->max_score }})</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($students as $student)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $student->last_name }}, {{ $student->first_name }}
                                </td>
                                @foreach($components as $component)
                                    @php
                                        $existingScore = isset($existingScores[$student->id]) 
                                            ? $existingScores[$student->id]->where('assessment_component_id', $component->id)->first() 
                                            : null;
                                    @endphp
                                    <td class="px-4 py-3 text-center">
                                        <input type="number" 
                                               name="scores[{{ $student->id }}][{{ $component->id }}]" 
                                               value="{{ $existingScore ? $existingScore->score : '' }}"
                                               min="0" 
                                               max="{{ $component->max_score }}" 
                                               step="0.01"
                                               class="w-20 text-center rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">
                    Save All Scores
                </button>
            </div>
        </form>
    @endif
@endsection