@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Result Sheet: {{ $class->name }}</h3>
            <p class="text-gray-600">Positions calculated automatically.</p>
        </div>
        <a href="/school/greenfield/results" class="text-blue-600 hover:underline">&larr; Back</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pos</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total Score</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Average</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($results as $res)
                    @php
                        $approval = \App\Models\Tenant\ResultApproval::where('student_id', $res['student']->id)
                            ->where('term_id', $termId)->where('academic_session_id', $sessionId)->first();
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-sm font-bold text-gray-900">{{ $res['position'] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $res['student']->last_name }}, {{ $res['student']->first_name }}</td>
                        <td class="px-4 py-3 text-center text-sm font-bold text-blue-600">{{ $res['total'] }}</td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">{{ $res['average'] }}%</td>
                        <td class="px-4 py-3 text-center text-sm">
                            @if($approval)
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs uppercase">{{ str_replace('_', ' ', $approval->status) }}</span>
                                <br>
                                @if(Auth::user()->role === 'class_teacher' && $approval->status === 'class_teacher_pending')
                                    <form method="POST" action="/school/greenfield/results/approve" class="mt-2 inline">@csrf
                                        <input type="hidden" name="student_id" value="{{ $res['student']->id }}">
                                        <input type="hidden" name="term_id" value="{{ $termId }}">
                                        <input type="hidden" name="session_id" value="{{ $sessionId }}">
                                        <button type="submit" class="bg-green-600 text-white px-2 py-1 rounded text-xs">Approve</button>
                                    </form>
                                @endif
                            @endif
                            <a href="/school/greenfield/results/report-card/{{ $res['student']->id }}?term_id={{ $termId }}&session_id={{ $sessionId }}" class="text-blue-600 hover:underline block mt-1 text-xs">View Report Card</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection