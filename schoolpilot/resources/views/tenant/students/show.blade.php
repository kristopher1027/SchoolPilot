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
    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
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
    </div>

    <section class="bg-white rounded-lg shadow p-5 mb-5">
        <h4 class="font-semibold text-gray-800 mb-2">Record a placement or status change</h4>
        <p class="text-sm text-gray-500 mb-4">Use this workflow for promotion, class/section transfer, withdrawal or graduation. Each change is saved to the student's history.</p>
        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/students/' . $student->id . '/lifecycle') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            <label class="text-sm text-gray-700">Section
                <select name="section_id" required class="mt-1 w-full rounded-md border-gray-300">
                    @foreach($sections as $section)<option value="{{ $section->id }}" @selected((string) old('section_id', $student->section_id) === (string) $section->id)>{{ $section->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm text-gray-700">Class
                <select name="class_id" class="mt-1 w-full rounded-md border-gray-300">
                    <option value="">Unassigned</option>
                    @foreach($classes as $class)<option value="{{ $class->id }}" @selected((string) old('class_id', $student->class_id) === (string) $class->id)>{{ $class->name }} — {{ $class->section?->name ?? 'Section' }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm text-gray-700">Student status
                <select name="status" required class="mt-1 w-full rounded-md border-gray-300">
                    @foreach(['active' => 'Active', 'graduated' => 'Graduated', 'transferred' => 'Transferred', 'withdrawn' => 'Withdrawn'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $student->status) === $value)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm text-gray-700">Effective date<input type="date" name="effective_on" value="{{ old('effective_on', now()->toDateString()) }}" required class="mt-1 w-full rounded-md border-gray-300"></label>
            <label class="text-sm text-gray-700 sm:col-span-2">Reason / notes<textarea name="reason" rows="3" required maxlength="2000" class="mt-1 w-full rounded-md border-gray-300" placeholder="e.g. Promoted after successful completion of the academic year">{{ old('reason') }}</textarea></label>
            <div class="sm:col-span-2 flex justify-end"><button class="bg-blue-600 text-white px-5 py-2 rounded">Save lifecycle change</button></div>
        </form>
    </section>

    <section class="bg-white rounded-lg shadow p-5 mb-5">
        <h4 class="font-semibold text-gray-800 mb-4">Student History</h4>
        @forelse($student->enrollmentHistory as $entry)
            <div class="border-l-2 border-blue-500 pl-4 py-2 mb-3">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                    <p class="font-medium text-gray-800">{{ ucwords(str_replace('_', ' ', $entry->change_type)) }}</p>
                    <p class="text-sm text-gray-500">{{ $entry->effective_on?->format('d M Y') }}</p>
                </div>
                <p class="text-sm text-gray-700">
                    {{ $entry->fromSection?->name ?? '—' }} / {{ $entry->fromClass?->name ?? 'Unassigned' }}
                    → {{ $entry->toSection?->name ?? '—' }} / {{ $entry->toClass?->name ?? 'Unassigned' }}
                    · {{ ucfirst($entry->from_status ?? 'New') }} → {{ ucfirst($entry->to_status) }}
                </p>
                <p class="text-sm text-gray-600">{{ $entry->reason }}</p>
                <p class="text-xs text-gray-400">Recorded by {{ $entry->changedBy?->name ?? 'System' }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-500">No enrollment history has been recorded yet.</p>
        @endforelse
    </section>

    <section class="bg-white rounded-lg shadow p-5 mb-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div><h4 class="font-semibold text-gray-800">Parents & Guardians</h4><p class="text-sm text-gray-500">Link more than one guardian and mark the primary contact.</p></div>
            <a href="{{ url('/school/' . request()->route('school') . '/parents/create') }}" class="text-sm text-blue-600 hover:underline">+ Add guardian record</a>
        </div>
        @if($student->guardians->isNotEmpty())
            <div class="space-y-3 mb-5">
                @foreach($student->guardians as $guardian)
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border rounded p-3">
                        <div><p class="font-medium text-gray-800">{{ $guardian->user?->name ?? 'Unknown' }} @if($guardian->pivot->is_primary)<span class="ml-2 text-xs bg-blue-100 text-blue-700 rounded px-2 py-1">Primary contact</span>@endif</p><p class="text-sm text-gray-600">{{ $guardian->pivot->relationship ?: 'Guardian' }} · {{ $guardian->user?->phone ?: 'No phone' }} · {{ $guardian->user?->email }} @if($guardian->pivot->is_emergency_contact)<span class="ml-2 text-xs bg-amber-100 text-amber-800 rounded px-2 py-1">Emergency contact</span>@endif @if($guardian->pivot->can_pickup)<span class="ml-2 text-xs bg-gray-100 text-gray-700 rounded px-2 py-1">Pickup authorized</span>@endif</p></div>
                        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/students/' . $student->id . '/guardians/' . $guardian->id) }}" onsubmit="return confirm('Unlink this guardian from the student?')">@csrf @method('DELETE')<button class="text-sm text-red-600 hover:underline">Unlink</button></form>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 mb-4">No guardian has been linked to this student yet.</p>
        @endif
        @if($availableGuardians->isNotEmpty())
            <form method="POST" action="{{ url('/school/' . request()->route('school') . '/students/' . $student->id . '/guardians') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                @csrf
                <label class="text-sm text-gray-700 sm:col-span-2">Select guardian<select name="guardian_id" required class="mt-1 w-full rounded-md border-gray-300"><option value="">Choose a parent / guardian</option>@foreach($availableGuardians as $guardian)<option value="{{ $guardian->id }}">{{ $guardian->user?->name }} — {{ $guardian->user?->email }}</option>@endforeach</select></label>
                <label class="text-sm text-gray-700">Relationship to this student<input name="relationship" required maxlength="100" value="{{ old('relationship') }}" placeholder="Mother, father, aunt..." class="mt-1 w-full rounded-md border-gray-300"></label>
                <div class="sm:col-span-3 flex flex-wrap gap-4 text-sm text-gray-700">
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_primary" value="1"> Primary contact</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_emergency_contact" value="1"> Emergency contact</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="can_pickup" value="1"> Authorized to pick up student</label>
                </div>
                <div class="sm:col-span-3"><button class="bg-blue-600 text-white px-4 py-2 rounded">Link Guardian</button></div>
            </form>
        @else
            <p class="text-sm text-gray-500">Create a parent/guardian record before linking one.</p>
        @endif
    </section>

    <section class="bg-white rounded-lg shadow p-5">
        <h4 class="font-semibold text-gray-800 mb-4">Health Information</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div><dt class="text-gray-500">Blood group</dt><dd class="font-medium">{{ $student->blood_group ?: '—' }}</dd></div>
            <div><dt class="text-gray-500">Genotype</dt><dd class="font-medium">{{ $student->genotype ?: '—' }}</dd></div>
            <div><dt class="text-gray-500">Allergies</dt><dd class="font-medium">{{ $student->allergies ?: '—' }}</dd></div>
        </dl>
    </section>
@endsection
