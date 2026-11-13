<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\LibraryBook;
use App\Models\Tenant\LibraryCategory;
use App\Models\Tenant\LibraryCopy;
use App\Models\Tenant\LibraryLoan;
use App\Models\Tenant\LibraryReservation;
use App\Models\Tenant\Student;
use App\Models\Tenant\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:library_categories,id'],
            'status' => ['nullable', Rule::in(['available', 'issued', 'overdue'])],
        ]);

        $books = LibraryBook::with(['category', 'copies'])
            ->withCount(['copies as available_copies' => fn ($query) => $query->where('status', 'available')])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim($request->search);
                $query->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', '%' . $term . '%')
                        ->orWhere('author', 'like', '%' . $term . '%')
                        ->orWhere('isbn', 'like', '%' . $term . '%')
                        ->orWhereHas('copies', fn ($copies) => $copies->where('accession_no', 'like', '%' . $term . '%'));
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->category_id))
            ->when($request->status === 'available', fn ($query) => $query->whereHas('copies', fn ($copies) => $copies->where('status', 'available')))
            ->when($request->status === 'issued', fn ($query) => $query->whereHas('copies', fn ($copies) => $copies->where('status', 'issued')))
            ->orderBy('title')->paginate(15)->withQueryString();

        $categories = LibraryCategory::orderBy('name')->get();
        $activeLoans = LibraryLoan::with('copy.book')->whereNull('returned_at')->orderBy('due_at')->get();
        $overdueLoans = $activeLoans->filter(fn ($loan) => $loan->due_at->lt(today()));
        $recentLoans = LibraryLoan::with('copy.book')->latest('issued_at')->limit(10)->get();
        $students = Student::where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get();
        $staff = Staff::with('user')->orderBy('id')->get();

        return view('tenant.library.index', compact(
            'books', 'categories', 'activeLoans', 'overdueLoans', 'recentLoans', 'students', 'staff'
        ));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:library_categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        LibraryCategory::create($data);

        return back()->with('success', 'Library category created.');
    }

    public function storeBook(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'author' => ['required', 'string', 'max:160'],
            'category_id' => ['nullable', 'integer', 'exists:library_categories,id'],
            'isbn' => ['nullable', 'string', 'max:32', 'unique:library_books,isbn'],
            'publisher' => ['nullable', 'string', 'max:160'],
            'edition' => ['nullable', 'string', 'max:80'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:' . (now()->year + 1)],
            'description' => ['nullable', 'string', 'max:3000'],
            'accession_numbers' => ['required', 'string', 'max:8000'],
        ]);

         $accessionNumbers = collect(preg_split('/\r\n|\r|\n/', $data['accession_numbers']))
            ->map(fn ($value) => trim($value))->filter()->values();
        if ($accessionNumbers->isEmpty() || $accessionNumbers->count() > 100 || $accessionNumbers->unique()->count() !== $accessionNumbers->count()) {
            return back()->withErrors(['accession_numbers' => 'Enter between 1 and 100 unique accession numbers, one per line.'])->withInput();
        }
        foreach ($accessionNumbers as $accessionNo) {
            if (LibraryCopy::where('accession_no', $accessionNo)->exists()) {
                return back()->withErrors(['accession_numbers' => 'Accession number ' . $accessionNo . ' is already in use.'])->withInput();
            }
        }

        DB::transaction(function () use ($data, $accessionNumbers) {
            $book = LibraryBook::create(collect($data)->except('accession_numbers')->all());
            foreach ($accessionNumbers as $accessionNo) {
                $book->copies()->create(['accession_no' => trim($accessionNo)]);
            }
        });

        return back()->with('success', 'Book and copy records created.');
    }

    public function issue(Request $request)
    {
        $data = $request->validate([
            'copy_id' => ['required', 'integer', 'exists:library_copies,id'],
            'member' => ['required', 'regex:/^(student|staff):[0-9]+$/],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        [$memberType, $memberId] = explode(':', $data['member']);
        $memberExists = $memberType === 'student'
            ? Student::whereKey($memberId)->where('status', 'active')->exists()
            : Staff::whereKey($memberId)->exists();

        if (! $memberExists) {
            return back()->withErrors(['member_id' => 'Select an active student or valid staff member.'])->withInput();
        }

        DB::transaction(function () use ($data, $memberType, $memberId) {
            $copy = LibraryCopy::whereKey($data['copy_id'])->lockForUpdate()->firstOrFail();
            abort_unless($copy->status === 'available', 422, 'This copy is not currently available.');

            $user = Auth::guard('school')->user();
            LibraryLoan::create([
                'copy_id' => $copy->id,
                'member_type' => $memberType,
                'member_id' => $memberId,
                'issued_by' => $user->id,
                'issued_at' => today(),
                'due_at' => $data['due_at'],
            ]);
            $copy->update(['status' => 'issued']);

            LibraryReservation::where('book_id', $copy->book_id)
                ->where('member_type', $memberType)
                ->where('member_id', $memberId)
                ->where('status', 'pending')
                ->oldest('reserved_at')
                ->limit(1)
                ->update(['status' => 'fulfilled', 'fulfilled_at' => now()]);
        });

        return back()->with('success', 'Library copy issued successfully.');
    }

    public function returnCopy(Request $request, LibraryLoan $loan)
    {
        $data = $request->validate([
            'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'fine_paid' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        DB::transaction(function () use ($loan, $data) {
            $loan = LibraryLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            abort_if($loan->returned_at !== null, 422, 'This loan has already been returned.');

            $fine = (float) ($data['fine_amount'] ?? 0);
            $paid = (float) ($data['fine_paid'] ?? 0);
            abort_if($paid > $fine, 422, 'Fine paid cannot exceed the fine amount.');

            $loan->update([
                'returned_at' => today(),
                'returned_to' => Auth::guard('school')->id(),
                'fine_amount' => $fine,
                'fine_paid' => $paid,
            ]);
            $loan->copy()->update(['status' => 'available']);
        });

        return back()->with('success', 'Book returned and copy made available.');
    }

    public function renew(Request $request, LibraryLoan $loan)
    {
        $data = $request->validate([
            'due_at' => ['required', 'date', 'after:today'],
        ]);

        $loan = LibraryLoan::with('copy')->findOrFail($loan->id);
        abort_if($loan->returned_at !== null, 422, 'A returned loan cannot be renewed.');
        $hasReservation = LibraryReservation::where('book_id', $loan->copy->book_id)
            ->where('status', 'pending')->exists();
        abort_if($hasReservation, 422, 'This book has a pending reservation and cannot be renewed.');

        $loan->update(['due_at' => $data['due_at']]);

        return back()->with('success', 'Loan due date renewed.');
    }

    public function reserve(Request $request)
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:library_books,id'],
            'member' => ['required', 'regex:/^(student|staff):[0-9]+$/],
        ]);

        [$memberType, $memberId] = explode(':', $data['member']);
        $memberExists = $memberType === 'student'
            ? Student::whereKey($memberId)->where('status', 'active')->exists()
            : Staff::whereKey($memberId)->exists();

        if (! $memberExists) {
            return back()->withErrors(['member_id' => 'Select a valid library member.'])->withInput();
        }

        $alreadyReserved = LibraryReservation::where('book_id', $data['book_id'])
            ->where('member_type', $memberType)
            ->where('member_id', $memberId)
            ->where('status', 'pending')->exists();

        if ($alreadyReserved) {
            return back()->withErrors(['book_id' => 'This member already has a pending reservation for the book.']);
        }

        LibraryReservation::create([
            'book_id' => $data['book_id'],
            'member_type' => $memberType,
            'member_id' => $memberId,
            'status' => 'pending',
            'reserved_at' => now(),
        ]);

        return back()->with('success', 'Book reservation recorded.');
    }
}