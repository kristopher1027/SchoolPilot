@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Welcome back, {{ Auth::user()->name }}!</h3>
        <p class="text-gray-600">Here is what is happening at Greenfield Academy today.</p>
    </div>

        <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
            <h4 class="text-sm font-medium text-gray-500">Total Students</h4>
            <p class="text-3xl font-bold text-blue-900">{{ $totalStudents }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
            <h4 class="text-sm font-medium text-gray-500">Total Staff</h4>
            <p class="text-3xl font-bold text-green-900">{{ $totalStaff }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
            <h4 class="text-sm font-medium text-gray-500">Total Classes</h4>
            <p class="text-3xl font-bold text-purple-900">{{ $totalClasses }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-orange-500">
            <h4 class="text-sm font-medium text-gray-500">Current Session</h4>
            <p class="text-lg font-bold text-orange-500">{{ $currentSession ? $currentSession->name : 'Not Set' }}</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h4 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h4>
        <div class="flex flex-wrap gap-4">
            <a href="{{ url('/school/' . request()->route('school') . '/students/create') }}"
               class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Add New Student
            </a>
            <a href="{{ url('/school/' . request()->route('school') . '/staff/create') }}"
               class="inline-flex items-center bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700 transition focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                Add Staff Member
            </a>
            <a href="{{ url('/school/' . request()->route('school') . '/academic') }}"
               class="inline-flex items-center bg-purple-600 text-white px-4 py-2 rounded shadow hover:bg-purple-700 transition focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                Setup New Session
            </a>
        </div>
    </div>
@endsection