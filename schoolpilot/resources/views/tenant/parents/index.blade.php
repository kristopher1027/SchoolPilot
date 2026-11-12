@extends('tenant.layouts.app')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Parents & Guardians</h3>
            <p class="text-sm text-gray-500">Manage parent contact records and their linked students.</p>
        </div>
        <a href="{{ url('/school/' . request()->route('school') . '/parents/create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-center">Add Parent / Guardian</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="status">{{ session('success') }}</div>
    @endif

    <form method="GET" class="bg-white rounded-lg shadow p-4 mb-6 flex flex-col sm:flex-row gap-3">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, email or phone" class="flex-1 rounded-md border-gray-300">
        <button class="bg-slate-800 text-white rounded-md px-5 py-2">Search</button>
        <a href="{{ url('/school/' . request()->route('school') . '/parents') }}" class="border rounded-md px-4 py-2 text-center">Reset</a>
    </form>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email / Phone</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Relationship</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Linked students</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($guardians as $guardian)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $guardian->user?->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $guardian->user?->email }}<br>{{ $guardian->user?->phone ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $guardian->relationship ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $guardian->students_count }}</td>
                        <td class="px-4 py-3 text-right"><a class="text-blue-600 hover:underline" href="{{ url('/school/' . request()->route('school') . '/parents/' . $guardian->id . '/edit') }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">No parent or guardian records found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t">{{ $guardians->links() }}</div>
    </div>
@endsection
