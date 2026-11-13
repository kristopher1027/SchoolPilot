@extends('tenant.layouts.app')

@section('content')
<div class="mb-6">
    <h3 class="text-2xl font-bold text-gray-800">Library Management</h3>
    <p class="text-gray-600">Manage the book catalogue, copies, circulation and overdue items.</p>
</div>

@if(session('success'))
    <div class="mb-4 rounded border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded border border-red-200 bg-red-50 p-4 text-red-800">
        <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg bg-white p-4 shadow"><p class="text-sm text-gray-500">Active loans</p><p class="text-2xl font-bold">{{ $activeLoans->count() }}</p></div>
    <div class="rounded-lg bg-white p-4 shadow"><p class="text-sm text-gray-500">Overdue loans</p><p class="text-2xl font-bold text-red-700">{{ $overdueLoans->count() }}</p></div>
    <div class="rounded-lg bg-white p-4 shadow"><p class="text-sm text-gray-500">Book titles</p><p class="text-2xl font-bold">{{ $books->total() }}</p></div>
</div>

<div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <section class="rounded-lg bg-white p-5 shadow">
        <h4 class="mb-4 font-bold">Add book and copies</h4>
        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/library/books') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @csrf
            <input name="title" required maxlength="200" placeholder="Book title" class="rounded border-gray-300">
            <input name="author" required maxlength="160" placeholder="Author" class="rounded border-gray-300">
            <select name="category_id" class="rounded border-gray-300"><option value="">No category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
            <input name="isbn" maxlength="32" placeholder="ISBN (optional)" class="rounded border-gray-300">
            <input name="publisher" maxlength="160" placeholder="Publisher" class="rounded border-gray-300">
            <input name="edition" maxlength="80" placeholder="Edition" class="rounded border-gray-300">
            <input name="publication_year" type="number" min="1000" max="{{ now()->year + 1 }}" placeholder="Publication year" class="rounded border-gray-300">
            <textarea name="description" maxlength="3000" placeholder="Description" class="rounded border-gray-300 sm:col-span-2"></textarea>
            <label class="text-sm font-medium sm:col-span-2">Accession numbers — enter one per line</label>
            <textarea name="accession_numbers[]" required rows="3" placeholder="LIB-0001&#10;LIB-0002" class="rounded border-gray-300 sm:col-span-2"></textarea>
            <p class="text-xs text-gray-500 sm:col-span-2">For multiple copies, submit each accession number as a separate accession_numbers[] field. Add copies individually if needed.</p>
            <button class="rounded bg-blue-700 px-4 py-2 font-semibold text-white sm:col-span-2">Save book</button>
        </form>
    </section>

    <section class="rounded-lg bg-white p-5 shadow">
        <h4 class="mb-4 font-bold">Add category</h4>
        <form method="POST" action="{{ url('/school/' . request()->route('school') . '/library/categories') }}" class="space-y-3">
            @csrf
            <input name="name" required maxlength="120" placeholder="Category name" class="w-full rounded border-gray-300">
            <textarea name="description" maxlength="1000" placeholder="Description (optional)" class="w-full rounded border-gray-300"></textarea>
            <button class="rounded bg-blue-700 px-4 py-2 font-semibold text-white">Save category</button>
        </form>
        <div class="mt-5 border-t pt-4"><h5 class="mb-2 text-sm font-semibold">Existing categories</h5>
            <div class="flex flex-wrap gap-2">@forelse($categories as $category)<span class="rounded-full bg-gray-100 px-3 py-1 text-sm">{{ $category->name }}</span>@empty<p class="text-sm text-gray-500">No categories created yet.</p>@endforelse</div>
        </div>
    </section>
</div>

<section class="mb-6 rounded-lg bg-white p-5 shadow">
    <h4 class="mb-4 font-bold">Issue a book</h4>
    <form method="POST" action="{{ url('/school/' . request()->route('school') . '/library/loans') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
        @csrf
        <select name="copy_id" required class="rounded border-gray-300"><option value="">Available copy</option>@foreach($books as $book)@foreach($book->copies->where('status', 'available') as $copy)<option value="{{ $copy->id }}">{{ $book->title }} — {{ $copy->accession_no }}</option>@endforeach @endforeach</select>
        <select name="member_type" id="issue-member-type" required class="rounded border-gray-300"><option value="student">Student</option><option value="staff">Staff</option></select>
        <select name="member_id" required class="rounded border-gray-300"><optgroup label="Students">@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->admission_no }} — {{ $student->first_name }} {{ $student->last_name }}</option>@endforeach</optgroup><optgroup label="Staff">@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->user?->name ?? 'Staff #' . $person->id }}</option>@endforeach</optgroup></select>
        <input type="date" name="due_at" required min="{{ today()->toDateString() }}" value="{{ today()->addDays(14)->toDateString() }}" class="rounded border-gray-300">
        <button class="rounded bg-green-700 px-4 py-2 font-semibold text-white md:col-span-4">Issue copy</button>
    </form>
    <p class="mt-2 text-xs text-gray-500">The member selector is separated by student/staff groups; choose an ID belonging to the selected member type.</p>
</section>

<section class="mb-6 rounded-lg bg-white p-5 shadow">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><h4 class="font-bold">Book catalogue</h4>
        <form method="GET" class="flex flex-wrap gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Title, author, ISBN or accession no." class="rounded border-gray-300">
            <select name="category_id" class="rounded border-gray-300"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>
            <button class="rounded bg-gray-800 px-3 py-2 text-white">Filter</button>
        </form>
    </div>
    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-3">Book</th><th class="px-3 py-3">Category</th><th class="px-3 py-3">ISBN</th><th class="px-3 py-3">Copies</th><th class="px-3 py-3">Available</th></tr></thead>
        <tbody class="divide-y divide-gray-100">@forelse($books as $book)<tr><td class="px-3 py-3"><strong>{{ $book->title }}</strong><div class="text-gray-500">{{ $book->author }}</div></td><td class="px-3 py-3">{{ $book->category?->name ?? '—' }}</td><td class="px-3 py-3">{{ $book->isbn ?? '—' }}</td><td class="px-3 py-3">{{ $book->copies->count() }}</td><td class="px-3 py-3">{{ $book->available_copies }}</td></tr>@empty<tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No books found.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-4">{{ $books->links() }}</div>
</section>

<section class="rounded-lg bg-white p-5 shadow">
    <h4 class="mb-4 font-bold">Active loans and overdue tracking</h4>
    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-3">Book / Copy</th><th class="px-3 py-3">Member</th><th class="px-3 py-3">Due date</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Actions</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($activeLoans as $loan)
            @php($member = $loan->member_type === 'student' ? $students->firstWhere('id', $loan->member_id) : $staff->firstWhere('id', $loan->member_id))
            <tr><td class="px-3 py-3">{{ $loan->copy?->book?->title }}<div class="text-gray-500">{{ $loan->copy?->accession_no }}</div></td><td class="px-3 py-3">{{ $loan->member_type === 'student' ? ($member?->first_name . ' ' . $member?->last_name) : ($member?->user?->name ?? 'Staff') }}</td><td class="px-3 py-3">{{ $loan->due_at->format('d M Y') }}</td><td class="px-3 py-3">@if($loan->due_at->lt(today()))<span class="font-semibold text-red-700">Overdue</span>@else<span class="text-green-700">On loan</span>@endif</td><td class="px-3 py-3">
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ url('/school/' . request()->route('school') . '/library/loans/' . $loan->id . '/return') }}" class="flex gap-1">@csrf<input type="number" name="fine_amount" min="0" step="0.01" placeholder="Fine" class="w-20 rounded border-gray-300"><input type="number" name="fine_paid" min="0" step="0.01" placeholder="Paid" class="w-20 rounded border-gray-300"><button class="rounded bg-blue-700 px-2 py-1 text-white">Return</button></form>
                    <form method="POST" action="{{ url('/school/' . request()->route('school') . '/library/loans/' . $loan->id . '/renew') }}" class="flex gap-1">@csrf<input type="date" name="due_at" min="{{ today()->addDay()->toDateString() }}" required class="rounded border-gray-300"><button class="rounded bg-gray-700 px-2 py-1 text-white">Renew</button></form>
                </div>
            </td></tr>
        @empty<tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No active loans.</td></tr>@endforelse
        </tbody>
    </table></div>
</section>
@endsection