@extends('tenant.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-6"><h2 class="text-2xl font-bold text-gray-800">Admission Application</h2><p class="mt-1 text-sm text-gray-500">Complete this form to apply for admission. The school will contact the parent or guardian about the next steps.</p></div>
    @if(session('application_number'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-5 text-green-900" role="status">
            <h3 class="font-semibold">Application submitted successfully</h3>
            <p class="mt-1 text-sm">Keep this application number for your records:</p>
            <p class="mt-2 text-xl font-bold">{{ session('application_number') }}</p>
        </div>
    @else
        @if($errors->any())<div class="mb-5 rounded bg-red-50 p-4 text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/admissions/apply') }}" class="space-y-6 rounded-lg bg-white p-6 shadow">
            @csrf
            <section><h3 class="mb-4 font-semibold text-gray-800">Applicant information</h3><div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm font-medium">First name *</label><input name="first_name" required maxlength="255" value="{{ old('first_name') }}" class="w-full rounded border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">Last name *</label><input name="last_name" required maxlength="255" value="{{ old('last_name') }}" class="w-full rounded border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">Middle name</label><input name="middle_name" maxlength="255" value="{{ old('middle_name') }}" class="w-full rounded border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">Gender *</label><select name="gender" required class="w-full rounded border-gray-300"><option value="">Select</option><option value="male" @selected(old('gender')==='male')>Male</option><option value="female" @selected(old('gender')==='female')>Female</option></select></div>
                <div><label class="mb-1 block text-sm font-medium">Date of birth *</label><input type="date" name="date_of_birth" required max="{{ now()->subDay()->toDateString() }}" value="{{ old('date_of_birth') }}" class="w-full rounded border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">Applying for section *</label><select name="section_id" required class="w-full rounded border-gray-300"><option value="">Select section</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected(old('section_id')==$section->id)>{{ $section->name }}</option>@endforeach</select></div>
                <div><label class="mb-1 block text-sm font-medium">Preferred class (optional)</label><select name="class_id" class="w-full rounded border-gray-300"><option value="">School to determine</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('class_id')==$class->id)>{{ $class->name }}</option>@endforeach</select><p class="mt-1 text-xs text-gray-500">The selected class must belong to the chosen section.</p></div>
                <div><label class="mb-1 block text-sm font-medium">Previous school</label><input name="previous_school" maxlength="255" value="{{ old('previous_school') }}" class="w-full rounded border-gray-300"></div>
            </div></section>
            <section class="border-t pt-5"><h3 class="mb-4 font-semibold text-gray-800">Parent / guardian contact</h3><div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm font-medium">Guardian full name *</label><input name="guardian_name" required maxlength="255" value="{{ old('guardian_name') }}" class="w-full rounded border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">Phone number *</label><input name="guardian_phone" required maxlength="40" value="{{ old('guardian_phone') }}" class="w-full rounded border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">Email address</label><input type="email" name="guardian_email" maxlength="255" value="{{ old('guardian_email') }}" class="w-full rounded border-gray-300"></div>
                <div class="sm:col-span-2"><label class="mb-1 block text-sm font-medium">Home address</label><textarea name="address" rows="3" maxlength="5000" class="w-full rounded border-gray-300">{{ old('address') }}</textarea></div>
            </div></section>
            <button class="rounded bg-blue-700 px-5 py-2.5 font-medium text-white hover:bg-blue-800">Submit Application</button>
        </form>
    @endif
</div>
@endsection