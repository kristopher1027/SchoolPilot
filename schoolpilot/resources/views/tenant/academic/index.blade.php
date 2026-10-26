@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Academic Setup</h3>
        <p class="text-gray-600">Manage Sessions, Classes, and Subjects.</p>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- COLUMN 1: Sessions & Terms -->
        <div class="space-y-6">
            <!-- Create Session -->
            <div class="bg-white rounded-lg shadow p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-4">1. Create Academic Session</h4>
                <form method="POST" action="/school/greenfield/academic/sessions" class="space-y-4">
                    @csrf
                    <input type="text" name="name" placeholder="e.g. 2025/2026" required class="w-full rounded-md border-gray-300 shadow-sm">
                    <div class="flex space-x-4">
                        <input type="date" name="start_date" required class="w-1/2 rounded-md border-gray-300 shadow-sm">
                        <input type="date" name="end_date" required class="w-1/2 rounded-md border-gray-300 shadow-sm">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded">Add Session</button>
                </form>
            </div>

            <!-- Create Term -->
            <div class="bg-white rounded-lg shadow p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-4">2. Create Term</h4>
                <form method="POST" action="/school/greenfield/academic/terms" class="space-y-4">
                    @csrf
                    <select name="academic_session_id" required class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select Session</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </select>
                    <select name="name" required class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="First Term">First Term</option>
                        <option value="Second Term">Second Term</option>
                        <option value="Third Term">Third Term</option>
                    </select>
                    <div class="flex space-x-4">
                        <input type="date" name="start_date" required class="w-1/2 rounded-md border-gray-300 shadow-sm">
                        <input type="date" name="end_date" required class="w-1/2 rounded-md border-gray-300 shadow-sm">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded">Add Term</button>
                </form>
            </div>
        </div>

        <!-- COLUMN 2: Classes & Subjects -->
        <div class="space-y-6">
            <!-- Create Class -->
            <div class="bg-white rounded-lg shadow p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-4">3. Create Class</h4>
                <form method="POST" action="/school/greenfield/academic/classes" class="space-y-4">
                    @csrf
                    <select name="section_id" required class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select Section</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" placeholder="e.g. Primary 1A" required class="w-full rounded-md border-gray-300 shadow-sm">
                    <input type="number" name="level" placeholder="Level (e.g. 1)" required class="w-full rounded-md border-gray-300 shadow-sm">
                    <button type="submit" class="w-full bg-green-600 text-white py-2 rounded">Add Class</button>
                </form>
            </div>

            <!-- Create Subject -->
            <div class="bg-white rounded-lg shadow p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-4">4. Create Subject</h4>
                <form method="POST" action="/school/greenfield/academic/subjects" class="space-y-4">
                    @csrf
                    <input type="text" name="name" placeholder="e.g. Mathematics" required class="w-full rounded-md border-gray-300 shadow-sm">
                    <select name="section_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All Sections (Optional)</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full bg-green-600 text-white py-2 rounded">Add Subject</button>
                </form>
            </div>
        </div>
    </div>
@endsection