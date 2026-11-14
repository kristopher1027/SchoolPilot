@extends('tenant.layouts.app')

@section('content')
@php($schoolBase = '/school/' . request()->route('school'))
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Academic Setup</h1>
    <p class="text-gray-600">Manage academic sessions, terms, classes and subjects for this school.</p>
</div>

@if(session('success'))
    <div role="status" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div role="alert" class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6">
        <p class="font-semibold">Please correct the following:</p>
        <ul class="list-disc pl-5 mt-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="space-y-6">
        <section class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Create Academic Session</h2>
            <form method="POST" action="{{ $schoolBase }}/academic/sessions" class="space-y-4">
                @csrf
                <label class="block text-sm font-medium text-gray-700" for="session_name">Session name</label>
                <input id="session_name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. 2026/2027" required maxlength="50" class="w-full rounded-md border-gray-300 shadow-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="session_start" class="block text-sm font-medium text-gray-700 mb-1">Start date</label><input id="session_start" type="date" name="start_date" value="{{ old('start_date') }}" required class="w-full rounded-md border-gray-300 shadow-sm"></div>
                    <div><label for="session_end" class="block text-sm font-medium text-gray-700 mb-1">End date</label><input id="session_end" type="date" name="end_date" value="{{ old('end_date') }}" required class="w-full rounded-md border-gray-300 shadow-sm"></div>
                </div>
                <button type="submit" class="w-full bg-blue-700 text-white py-2 rounded hover:bg-blue-800">Add Session</button>
            </form>
        </section>

        <section class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Create Term</h2>
            <form method="POST" action="{{ $schoolBase }}/academic/terms" class="space-y-4">
                @csrf
                <label for="academic_session_id" class="block text-sm font-medium text-gray-700">Academic session</label>
                <select id="academic_session_id" name="academic_session_id" required class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select session</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected(old('academic_session_id') == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <label for="term_name" class="block text-sm font-medium text-gray-700">Term</label>
                <select id="term_name" name="name" required class="w-full rounded-md border-gray-300 shadow-sm">
                    @foreach(['First Term', 'Second Term', 'Third Term'] as $termName)
                        <option value="{{ $termName }}" @selected(old('name') === $termName)>{{ $termName }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="term_start" class="block text-sm font-medium text-gray-700 mb-1">Start date</label><input id="term_start" type="date" name="start_date" value="{{ old('start_date') }}" required class="w-full rounded-md border-gray-300 shadow-sm"></div>
                    <div><label for="term_end" class="block text-sm font-medium text-gray-700 mb-1">End date</label><input id="term_end" type="date" name="end_date" value="{{ old('end_date') }}" required class="w-full rounded-md border-gray-300 shadow-sm"></div>
                </div>
                <button type="submit" class="w-full bg-blue-700 text-white py-2 rounded hover:bg-blue-800">Add Term</button>
            </form>
        </section>

        <section class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Academic Calendar</h2>
            @forelse($sessions as $session)
                <div class="border border-gray-200 rounded-lg p-4 mb-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $session->name }}
                                @if($session->is_current)<span class="ml-2 text-xs font-semibold text-green-700 bg-green-100 rounded-full px-2 py-1">Current session</span>@endif
                            </h3>
                            <p class="text-sm text-gray-500">{{ $session->start_date->format('d M Y') }} – {{ $session->end_date->format('d M Y') }}</p>
                        </div>
                        @unless($session->is_current)
                            <form method="POST" action="{{ $schoolBase }}/academic/sessions/{{ $session->id }}/current">
                                @csrf
                                <button class="text-sm font-medium text-blue-700 hover:underline" type="submit">Set as current</button>
                            </form>
                        @endunless
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse($session->terms as $term)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded bg-gray-50 px-3 py-2">
                                <div>
                                    <span class="text-sm font-medium text-gray-800">{{ $term->name }}</span>
                                    <span class="text-xs text-gray-500 ml-1">{{ $term->start_date->format('d M') }} – {{ $term->end_date->format('d M Y') }}</span>
                                    @if($term->is_current)<span class="ml-2 text-xs font-semibold text-green-700">Current term</span>@endif
                                </div>
                                @if($session->is_current && ! $term->is_current)
                                    <form method="POST" action="{{ $schoolBase }}/academic/terms/{{ $term->id }}/current">
                                        @csrf
                                        <button class="text-xs font-medium text-blue-700 hover:underline" type="submit">Set current</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No terms have been added to this session.</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No academic sessions created yet.</p>
            @endforelse
        </section>
    </div>

    <div class="space-y-6">
        <section class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Create Class</h2>
            <form method="POST" action="{{ $schoolBase }}/academic/classes" class="space-y-4">
                @csrf
                <label for="section_id" class="block text-sm font-medium text-gray-700">Section</label>
                <select id="section_id" name="section_id" required class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select section</option>
                    @foreach($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                    @endforeach
                </select>
                <label for="class_name" class="block text-sm font-medium text-gray-700">Class name</label>
                <input id="class_name" type="text" name="name" placeholder="e.g. Primary 1A" required maxlength="100" class="w-full rounded-md border-gray-300 shadow-sm">
                <label for="class_level" class="block text-sm font-medium text-gray-700">Level</label>
                <input id="class_level" type="number" name="level" placeholder="e.g. 1" min="1" max="20" required class="w-full rounded-md border-gray-300 shadow-sm">
                <button type="submit" class="w-full bg-green-700 text-white py-2 rounded hover:bg-green-800">Add Class</button>
            </form>
        </section>

        <section class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Create Subject</h2>
            <form method="POST" action="{{ $schoolBase }}/academic/subjects" class="space-y-4">
                @csrf
                <label for="subject_name" class="block text-sm font-medium text-gray-700">Subject name</label>
                <input id="subject_name" type="text" name="name" placeholder="e.g. Mathematics" required maxlength="100" class="w-full rounded-md border-gray-300 shadow-sm">
                <label for="subject_section" class="block text-sm font-medium text-gray-700">Section</label>
                <select id="subject_section" name="section_id" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">All sections (optional)</option>
                    @foreach($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="w-full bg-green-700 text-white py-2 rounded hover:bg-green-800">Add Subject</button>
            </form>
        </section>
    </div>
</div>
@endsection
