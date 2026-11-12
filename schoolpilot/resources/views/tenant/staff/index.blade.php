@extends('tenant.layouts.app')
@section('content')
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
 <div><h3 class="text-2xl font-bold text-gray-800">Staff Directory</h3><p class="text-sm text-gray-500">Search staff, review employment details and manage account access.</p></div>
 <a href="{{ url('/school/' . request()->route('school') . '/staff/create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">Add New Staff</a>
</div>
@if(session('success'))<div class="bg-green-100 text-green-800 p-3 rounded mb-5" role="status">{{ session('success') }}</div>@endif
<form method="GET" class="bg-white rounded-lg shadow p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
 <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, email, phone or staff number" class="rounded-md border-gray-300">
 <select name="role" class="rounded-md border-gray-300"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>@endforeach</select>
 <select name="department_id" class="rounded-md border-gray-300"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach</select>
 <select name="status" class="rounded-md border-gray-300"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
 <div class="sm:col-span-2 lg:col-span-4 flex gap-2"><button class="bg-slate-800 text-white rounded px-5 py-2">Apply filters</button><a href="{{ url('/school/' . request()->route('school') . '/staff') }}" class="border rounded px-4 py-2">Reset</a></div>
</form>
<div class="bg-white rounded-lg shadow overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
<thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs uppercase">Staff No.</th><th class="px-4 py-3 text-left text-xs uppercase">Name / Email</th><th class="px-4 py-3 text-left text-xs uppercase">Role / Designation</th><th class="px-4 py-3 text-left text-xs uppercase">Departments</th><th class="px-4 py-3 text-left text-xs uppercase">Status</th><th class="px-4 py-3 text-right text-xs uppercase">Action</th></tr></thead>
<tbody class="divide-y divide-gray-200">
@forelse($staff as $member)<tr>
<td class="px-4 py-3 text-sm font-medium">{{ $member->staff_no }}</td><td class="px-4 py-3 text-sm">{{ $member->user?->name ?? 'Unknown' }}<div class="text-xs text-gray-500">{{ $member->user?->email }}</div></td>
<td class="px-4 py-3 text-sm capitalize">{{ str_replace('_', ' ', $member->user?->role ?? '') }}<div class="text-xs text-gray-500">{{ $member->designation ?: '—' }}</div></td>
<td class="px-4 py-3 text-sm">{{ $member->departments->pluck('name')->join(', ') ?: '—' }}</td><td class="px-4 py-3 text-sm">{{ $member->user?->is_active ? 'Active' : 'Inactive' }}</td>
<td class="px-4 py-3 text-right text-sm"><a class="text-blue-600" href="{{ url('/school/' . request()->route('school') . '/staff/' . $member->id) }}">View</a> · <a class="text-blue-600" href="{{ url('/school/' . request()->route('school') . '/staff/' . $member->id . '/edit') }}">Edit</a></td></tr>
@empty<tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">No staff members match these filters.</td></tr>@endforelse
</tbody></table><div class="p-4 border-t">{{ $staff->links() }}</div></div>
@endsection
