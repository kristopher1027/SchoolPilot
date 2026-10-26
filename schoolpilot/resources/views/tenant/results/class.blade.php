@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Result Sheet: {{ $class->name }}</h3>
            <p class="text-gray-600">Positions calculated automatically.</p>
        </div>
        <a href="/school/greenfield/results" class="text-blue-600 hover:underline">&larr; Back</a>
    </div>

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
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">{{ $res['position'] }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                            {{ $res['student']->last_name }}, {{ $res['student']->first_name }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm font-bold text-blue-600">{{ $res['total'] }}</td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">{{ $res['average'] }}%</td>
                        <td class="px-4 py-3 text-center text-sm">
                            <a href="/school/greenfield/results/report-card/{{ $res['student']->id }}?term_id={{ $termId }}&session_id={{ $sessionId }}" class="text-blue-600 hover:underline font-semibold">View Report Card</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection