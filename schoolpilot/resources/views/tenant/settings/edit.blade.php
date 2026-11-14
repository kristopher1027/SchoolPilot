@extends('tenant.layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Administration</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900">School Settings</h1>
        <p class="mt-2 text-sm text-gray-600">Manage the school identity and contact information used across SchoolPilot.</p>
    </div>
    @if(session('success'))
        <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif
    <form method="POST" enctype="multipart/form-data" action="/school/{{ request()->route('school') }}/settings" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">
        @csrf
        @method('PUT')
        <div>
            <h2 class="text-lg font-semibold text-gray-900">School profile</h2>
            <p class="mt-1 text-sm text-gray-500">These details belong only to this school's database.</p>
        </div>
        <div class="rounded-lg border border-gray-200 p-4">
            <label for="logo" class="mb-2 block text-sm font-medium text-gray-700">School logo</label>
            @if($logoUrl)
                <div class="mb-3 flex items-center gap-3">
                    <img src="{{ $logoUrl }}" alt="Current school logo" class="h-16 w-16 rounded-lg border border-gray-200 object-contain p-1">
                    <p class="text-sm text-gray-500">Current logo</p>
                </div>
            @endif
            <input id="logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-700">
            <p class="mt-1 text-xs text-gray-500">PNG, JPG or WebP. Maximum size: 2 MB.</p>
            @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="school_name" class="mb-1 block text-sm font-medium text-gray-700">School name *</label>
                <input id="school_name" name="school_name" required maxlength="150" value="{{ old('school_name', $settings['school_name'] ?? '') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @error('school_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="motto" class="mb-1 block text-sm font-medium text-gray-700">School motto</label>
                <input id="motto" name="motto" maxlength="180" value="{{ old('motto', $settings['motto'] ?? '') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="school_type" class="mb-1 block text-sm font-medium text-gray-700">School type</label>
                <select id="school_type" name="school_type" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option value="">Select school type</option>
                    @foreach(['Nursery', 'Primary', 'Secondary', 'Nursery & Primary', 'Primary & Secondary', 'All-through'] as $type)
                        <option value="{{ $type }}" @selected(old('school_type', $settings['school_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Official email</label>
                <input id="email" type="email" name="email" maxlength="150" value="{{ old('email', $settings['email'] ?? '') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Phone number</label>
                <input id="phone" name="phone" maxlength="40" value="{{ old('phone', $settings['phone'] ?? '') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <label for="address" class="mb-1 block text-sm font-medium text-gray-700">School address</label>
                <textarea id="address" name="address" rows="3" maxlength="1000" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('address', $settings['address'] ?? '') }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label for="website" class="mb-1 block text-sm font-medium text-gray-700">Website URL</label>
                <input id="website" type="url" name="website" maxlength="255" placeholder="https://example.com" value="{{ old('website', $settings['website'] ?? '') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @error('website')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
            <a href="/school/{{ request()->route('school') }}/dashboard" class="rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700">Cancel</a>
            <button type="submit" class="rounded-lg bg-blue-700 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-800">Save changes</button>
        </div>
    </form>
</div>
@endsection
