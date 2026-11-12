@extends('tenant.layouts.app')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <a href="{{ url('/school/' . request()->route('school') . '/students') }}" class="text-sm text-blue-600 hover:underline">← Students directory</a>
            <h3 class="text-2xl font-bold text-gray-800 mt-2">{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</h3>
            <p class="text-sm text-gray-500">Admission number: {{ $student->admission_no }}</p>
        </div>
        <a href="{{ url('/school/' . request()->route('school') . '/students/' . $student->id . '/edit') }}" class="bg-blue-600 text-white px-4 py-2 rounded text-center">Edit Student</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="status">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow p-5 mb-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h4 class="font-semibold text-gray-800">Parents & Guardians</h4>
                <p class="text-sm text-gray-500">Link more than one guardian to this student and mark the primary contact.</p>
            </div>
            <a href="{{ url('/school/' . request()->route('school') . '/parents/create') }}" class="text-sm text-blue-600 hover:underline">+ Add guardian record</a>
        </div>
        @if($student->guardians->isNotEmpty())
            <div class="space-y-3 mb-5">
                @foreach($student->guardians as $guardian)
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border rounded p-3">
                        <div>
                            <p class="font-medium text-gray-800">{{ $guardian->user?->name ?? 'Unknown' }}
                                @if($guardian->pivot->is_primary)<span class="ml-2 text-xs bg-blue-100 text-blue-700 rounded px-2 py-1">Primary contact</span>@endif
                            </p>
                            <p class="text-sm text-gray-600">{{ $guardian->relationship ?: 'Guardian' }} · {{ $guardian->user?->phone ?: 'No phone' }} · {{ $guardian->user?->email }}</p>
                        </div>
                        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/students/' . $student->id . '/guardians/' . $guardian->id) }}" onsubmit="return confirm('Unlink this guardian from the student?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-sm text-red-600 hover:underline">Unlink</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 mb-4">No guardian has been linked to this student yet.</p>
        @endif
        @if($availableGuardians->isNotEmpty())
            <form method="POST" action="{{ url('/school/' . request()->route('school') . '/students/' . $student->id . '/guardians') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                @csrf
                <label class="text-sm text-gray-700 sm:col-span-2">Select guardian
                    <select name="guardian_id" required class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">Choose a parent / guardian</option>
                        @foreach($availableGuardians as $guardian)
                            <option value="{{ $guardian->id }}">{{ $guardian->user?->name }} — {{ $guardian->user?->email }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_primary" value="1"> Set as primary</label>
                <div class="sm:col-span-3"><button class="bg-blue-600 text-white px-4 py-2 rounded">Link Guardian</button></div>
            </form>
        @else
            <p class="text-sm text-gray-500">Create a parent/guardian record before linking one.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <section class="bg-white rounded-lg shadow p-5">
            <h4 class="font-semibold text-gray-800 mb-4">Personal Information</h4>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500">First name</dt><dd class="font-medium">{{ $student->first_name }}</dd></div>
                <div><dt class="text-gray-500">Last name</dt><dd class="font-medium">{{ $student->last_name }}</dd></div>
                <div><dt class="text-gray-500">Middle name</dt><dd class="font-medium">{{ $student->middle_name ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Gender</dt><dd class="font-medium capitalize">{{ $student->gender }}</dd></div>
                <div><dt class="text-gray-500">Date of birth</dt><dd class="font-medium">{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Date admitted</dt><dd class="font-medium">{{ $student->date_admitted?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ ucfirst($student->status) }}</dd></div>
                <div><dt class="text-gray-500">Address</dt><dd class="font-medium">{{ $student->address ?: '—' }}</dd></div>
            </dl>
        </section>
        <section class="bg-white rounded-lg shadow p-5">
            <h4 class="font-semibold text-gray-800 mb-4">Academic Placement</h4>
            <dl class="space-y-4 text-sm">
                <div><dt class="text-gray-500">Section</dt><dd class="font-medium">{{ $student->section?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Class</dt><dd class="font-medium">{{ $student->schoolClass?->name ?? 'Unassigned' }}</dd></div>
                <div><dt class="text-gray-500">Admission number</dt><dd class="font-medium">{{ $student->admission_no }}</dd></div>
            </dl>
        </section>
        <section class="bg-white rounded-lg shadow p-5 md:col-span-2">
            <h4 class="font-semibold text-gray-800 mb-4">Health Information</h4>
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div><dt class="text-gray-500">Blood group</dt><dd class="font-medium">{{ $student->blood_group ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Genotype</dt><dd class="font-medium">{{ $student->genotype ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Allergies</dt><dd class="font-medium">{{ $student->allergies ?: '—' }}</dd></div>
            </dl>
        </section>
    </div>
@endsection
