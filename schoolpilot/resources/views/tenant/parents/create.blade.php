@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ url('/school/' . request()->route('school') . '/parents') }}" class="text-sm text-blue-600 hover:underline">← Parents & Guardians</a>
        <h3 class="text-2xl font-bold text-gray-800 mt-2">Add Parent / Guardian</h3>
        <p class="text-sm text-gray-500">Create a contact record first. Link it to students from each student profile.</p>
    </div>

    @if($errors->any())
        <div class="bg-red-100 text-red-700 p-4 rounded mb-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ url('/school/' . request()->route('school') . '/parents') }}" class="bg-white rounded-lg shadow p-6 space-y-4 max-w-3xl">
        @csrf
        @include('tenant.parents._form')
        <div class="flex gap-3">
            <button class="bg-blue-600 text-white px-5 py-2 rounded">Save Guardian</button>
            <a href="{{ url('/school/' . request()->route('school') . '/parents') }}" class="border px-5 py-2 rounded">Cancel</a>
        </div>
    </form>
@endsection
