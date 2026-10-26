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
            <p class="text-3xl font-bold text-blue-900">0</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
            <h4 class="text-sm font-medium text-gray-500">Total Staff</h4>
            <p class="text-3xl font-bold text-green-900">1</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
            <h4 class="text-sm font-medium text-gray-500">Current Session</h4>
            <p class="text-lg font-bold text-purple-900">Not Set</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-orange-500">
            <h4 class="text-sm font-medium text-gray-500">Pending Results</h4>
            <p class="text-3xl font-bold text-orange-500">0</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h4 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h4>
        <div class="flex flex-wrap gap-4">
            <button class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">Add New Student</button>
            <button class="bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700 transition">Add Staff Member</button>
            <button class="bg-purple-600 text-white px-4 py-2 rounded shadow hover:bg-purple-700 transition">Setup New Session</button>
        </div>
    </div>
@endsection