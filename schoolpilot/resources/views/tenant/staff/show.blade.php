@extends('tenant.layouts.app')
@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between gap-3 mb-6">
 <div><a class="text-sm text-blue-600" href="{{ url('/school/' . request()->route('school') . '/staff') }}">← Staff directory</a><h3 class="text-2xl font-bold mt-2">{{ $staff->user?->name ?? 'Staff profile' }}</h3><p class="text-sm text-gray-500">{{ $staff->staff_no }}</p></div>
 <div class="flex gap-2"><a class="bg-blue-600 text-white px-4 py-2 rounded" href="{{ url('/school/' . request()->route('school') . '/staff/' . $staff->id . '/edit') }}">Edit profile</a><form method="POST" action="{{ url('/school/' . request()->route('school') . '/staff/' . $staff->id . '/status') }}">@csrf<button class="border px-4 py-2 rounded" onclick="return confirm('Change this staff account status?')">{{ $staff->user?->is_active ? 'Deactivate account' : 'Activate account' }}</button></form></div>
</div>
@if(session('success'))<div class="bg-green-100 text-green-800 p-3 rounded mb-4" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="bg-red-100 text-red-700 p-3 rounded mb-4">{{ $errors->first() }}</div>@endif
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
<section class="bg-white rounded-lg shadow p-5"><h4 class="font-semibold mb-4">Account details</h4><dl class="space-y-3 text-sm">
<div><dt class="text-gray-500">Name</dt><dd>{{ $staff->user?->name }}</dd></div><div><dt class="text-gray-500">Email</dt><dd>{{ $staff->user?->email }}</dd></div><div><dt class="text-gray-500">Phone</dt><dd>{{ $staff->user?->phone ?: '—' }}</dd></div><div><dt class="text-gray-500">Role</dt><dd class="capitalize">{{ str_replace('_', ' ', $staff->user?->role ?? '') }}</dd></div><div><dt class="text-gray-500">Account status</dt><dd>{{ $staff->user?->is_active ? 'Active' : 'Inactive' }}</dd></div>
</dl></section>
<section class="bg-white rounded-lg shadow p-5"><h4 class="font-semibold mb-4">Employment details</h4><dl class="space-y-3 text-sm">
<div><dt class="text-gray-500">Staff number</dt><dd>{{ $staff->staff_no }}</dd></div><div><dt class="text-gray-500">Designation</dt><dd>{{ $staff->designation ?: '—' }}</dd></div><div><dt class="text-gray-500">Date hired</dt><dd>{{ $staff->date_hired?->format('d M Y') ?? '—' }}</dd></div><div><dt class="text-gray-500">Qualification</dt><dd>{{ $staff->qualification ?: '—' }}</dd></div><div><dt class="text-gray-500">Departments</dt><dd>{{ $staff->departments->pluck('name')->join(', ') ?: '—' }}</dd></div><div><dt class="text-gray-500">Address</dt><dd>{{ $staff->address ?: '—' }}</dd></div>
</dl></section></div>
@endsection
