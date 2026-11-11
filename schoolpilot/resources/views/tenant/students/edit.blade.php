@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ url('/school/' . request()->route('school') . '/students/' . $student->id) }}" class="text-sm text-blue-600 hover:underline">← Back to student profile</a>
        <h3 class="text-2xl font-bold text-gray-800 mt-2">Edit Student Record</h3>
        <p class="text-sm text-gray-500">{{ $student->admission_no }}</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6 max-w-4xl">
        @if($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/students/' . $student->id) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="text-sm font-medium text-gray-700">First name<input name="first_name" value="{{ old('first_name', $student->first_name) }}" required class="mt-1 w-full rounded-md border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700">Last name<input name="last_name" value="{{ old('last_name', $student->last_name) }}" required class="mt-1 w-full rounded-md border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700">Middle name<input name="middle_name" value="{{ old('middle_name', $student->middle_name) }}" class="mt-1 w-full rounded-md border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700">Admission number<input name="admission_no" value="{{ old('admission_no', $student->admission_no) }}" required class="mt-1 w-full rounded-md border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700">Gender<select name="gender" required class="mt-1 w-full rounded-md border-gray-300"><option value="male" @selected(old('gender', $student->gender) === 'male')>Male</option><option value="female" @selected(old('gender', $student->gender) === 'female')>Female</option></select></label>
                <label class="text-sm font-medium text-gray-700">Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth', $student->date_of_birth?->format('Y-m-d')) }}" required class="mt-1 w-full rounded-md border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700">Date admitted<input type="date" name="date_admitted" value="{{ old('date_admitted', $student->date_admitted?->format('Y-m-d')) }}" required class="mt-1 w-full rounded-md border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700">Section<select name="section_id" required class="mt-1 w-full rounded-md border-gray-300">@foreach($sections as $section)<option value="{{ $section->id }}" @selected((string) old('section_id', $student->section_id) === (string) $section->id)>{{ $section->name }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-gray-700">Class<select name="class_id" class="mt-1 w-full rounded-md border-gray-300"><option value="">Unassigned</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected((string) old('class_id', $student->class_id) === (string) $class->id)>{{ $class->name }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-gray-700">Student status<select name="status" required class="mt-1 w-full rounded-md border-gray-300">@foreach(['active' => 'Active', 'graduated' => 'Graduated', 'transferred' => 'Transferred', 'withdrawn' => 'Withdrawn'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $student->status) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-gray-700">Blood group<input name="blood_group" value="{{ old('blood_group', $student->blood_group) }}" class="mt-1 w-full rounded-md border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700">Genotype<input name="genotype" value="{{ old('genotype', $student->genotype) }}" class="mt-1 w-full rounded-md border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700 sm:col-span-2">Address<textarea name="address" rows="2" class="mt-1 w-full rounded-md border-gray-300">{{ old('address', $student->address) }}</textarea></label>
                <label class="text-sm font-medium text-gray-700 sm:col-span-2">Allergies / relevant health notes<textarea name="allergies" rows="2" class="mt-1 w-full rounded-md border-gray-300">{{ old('allergies', $student->allergies) }}</textarea></label>
            </div>
            <div class="flex justify-end gap-3"><a href="{{ url('/school/' . request()->route('school') . '/students/' . $student->id) }}" class="px-4 py-2 border rounded">Cancel</a><button class="bg-blue-600 text-white px-5 py-2 rounded">Save Changes</button></div>
        </form>
    </div>
@endsection
