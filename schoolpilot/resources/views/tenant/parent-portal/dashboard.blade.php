@extends('tenant.layouts.app')
@section('content')
<div class="mb-6">
    <h3 class="text-2xl font-bold text-gray-800">Parent Portal</h3>
    <p class="text-sm text-gray-500">A read-only view of your linked children's school information.</p>
</div>

@if($guardian->students->isEmpty())
    <div class="rounded-lg bg-white p-8 text-center shadow">
        <h4 class="font-semibold text-gray-800">No students linked yet</h4>
        <p class="mt-2 text-sm text-gray-500">Please contact the school office to link your parent account to a student profile.</p>
    </div>
@else
    <div class="space-y-6">
        @foreach($guardian->students as $student)
            <section class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-800">{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</h4>
                            <p class="text-sm text-gray-500">{{ $student->admission_no }} · {{ $student->section?->name }} · {{ $student->schoolClass?->name }}</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium capitalize text-blue-700">{{ $student->status }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 p-5 lg:grid-cols-2">
                    <div>
                        <h5 class="mb-3 font-semibold text-gray-800">Recent attendance</h5>
                        @php($studentAttendance = $attendance->get($student->id, collect()))
                        @if($studentAttendance->isEmpty())
                            <p class="text-sm text-gray-500">No attendance records available yet.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($studentAttendance as $record)
                                    <div class="flex items-center justify-between rounded border p-3">
                                        <span class="text-sm">{{ $record->date?->format('d M Y') }}</span>
                                        <span class="text-sm font-medium capitalize
                                            {{ $record->status === 'present' ? 'text-green-700' : ($record->status === 'absent' ? 'text-red-700' : 'text-amber-700') }}">{{ $record->status }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <h5 class="mb-3 font-semibold text-gray-800">Published results</h5>
                        @php($studentResults = $publishedResults->get($student->id, collect()))
                        @if($studentResults->isEmpty())
                            <p class="text-sm text-gray-500">No published results available yet.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($studentResults as $result)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded border p-3">
                                        <div>
                                            <p class="text-sm font-medium">{{ $result->session_name }}</p>
                                            <p class="text-xs text-gray-500">{{ $result->term_name }}</p>
                                        </div>
                                        <a class="text-sm font-medium text-blue-700 hover:underline" href="{{ url('/school/' . request()->route('school') . '/parent-portal/results/' . $student->id . '?term_id=' . $result->term_id . '&session_id=' . $result->academic_session_id) }}">View report card</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach
    </div>
@endif
@endsection
