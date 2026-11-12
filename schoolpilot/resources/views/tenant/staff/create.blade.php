@extends('tenant.layouts.app')

@section('content')
    <div class="mb-6 flex items-center space-x-4">
        <a href="{{ url('/school/' . request()->route('school') . '/staff') }}" class="text-blue-600 hover:underline">&larr; Back to List</a>
        <h3 class="text-2xl font-bold text-gray-800">Add New Staff Member</h3>
    </div>

    <div class="bg-white rounded-lg shadow p-6 max-w-3xl">
        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/staff') }}">
            @csrf
            
            @if($errors->any())
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <ul class="list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Account Info -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">System Role</label>
                    <select name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" {{ old('role') == $role ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Initial Password</label>
                    <input type="password" name="password" minlength="12" autocomplete="new-password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-gray-500">Use at least 12 characters. Share it securely and ask the staff member to change it.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <!-- Employment Info -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Staff Number</label>
                    <input type="text" name="staff_no" value="{{ old('staff_no') }}" placeholder="e.g. GFA/STF/001" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Designation (Optional)</label>
                    <input type="text" name="designation" value="{{ old('designation') }}" placeholder="e.g. Senior Physics Teacher" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Date Hired</label>
                    <input type="date" name="date_hired" value="{{ old('date_hired', date('Y-m-d')) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Qualification</label>
                    <input type="text" name="qualification" value="{{ old('qualification') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('address') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <span class="block text-sm font-medium text-gray-700 mb-2">Departments</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @forelse($departments as $department)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="department_ids[]" value="{{ $department->id }}" @checked(in_array($department->id, old('department_ids', [])))> {{ $department->name }}</label>
                        @empty
                            <p class="text-sm text-gray-500">No departments configured yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">
                    Create Staff Account
                </button>
            </div>
        </form>
    </div>
@endsection