@extends('tenant.layouts.app')
@section('content')
<div class="mb-6"><a class="text-sm text-blue-600" href="{{ url('/school/' . request()->route('school') . '/staff/' . $staff->id) }}">← Staff profile</a><h3 class="text-2xl font-bold mt-2">Edit Staff Member</h3></div>
<form method="POST" action="{{ url('/school/' . request()->route('school') . '/staff/' . $staff->id) }}" class="bg-white rounded-lg shadow p-6 max-w-4xl">@csrf @method('PUT')
@include('tenant.staff._form')
<div class="mt-6 flex gap-3"><button class="bg-blue-600 text-white px-5 py-2 rounded">Save changes</button><a class="border px-5 py-2 rounded" href="{{ url('/school/' . request()->route('school') . '/staff/' . $staff->id) }}">Cancel</a></div>
</form>
@endsection
