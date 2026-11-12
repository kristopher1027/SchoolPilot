@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ url('/school/' . request()->route('school') . '/parents') }}" class="text-sm text-blue-600 hover:underline">← Parents & Guardians</a>
        <h3 class="text-2xl font-bold text-gray-800 mt-2">Edit Parent / Guardian</h3>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-5" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-100 text-red-700 p-4 rounded mb-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ url('/school/' . request()->route('school') . '/parents/' . $guardian->id) }}" class="bg-white rounded-lg shadow p-6 space-y-4 max-w-3xl">
        @csrf
        @method('PUT')
        @include('tenant.parents._form')
        <div class="flex gap-3">
            <button class="bg-blue-600 text-white px-5 py-2 rounded">Update Guardian</button>
            <a href="{{ url('/school/' . request()->route('school') . '/parents') }}" class="border px-5 py-2 rounded">Back</a>
        </div>
    </form>
@endsection
