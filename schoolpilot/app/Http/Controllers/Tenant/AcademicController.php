<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Section;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicController extends Controller
{
    public function index()
    {
        $sessions = AcademicSession::with('terms')->orderByDesc('start_date')->get();
        $sections = Section::all();
        $classes = SchoolClass::with('section')->get();
        $subjects = Subject::with('section')->get();

        return view('tenant.academic.index', compact('sessions', 'sections', 'classes', 'subjects'));
    }

    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $duplicate = AcademicSession::where('name', $validated['name'])->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'An academic session with this name already exists.',
            ]);
        }

        AcademicSession::create($validated + ['is_current' => false]);

        return back()->with('success', 'Academic session created successfully.');
    }

    public function storeTerm(Request $request)
    {
        $validated = $request->validate([
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'name' => ['required', 'in:First Term,Second Term,Third Term'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $session = AcademicSession::findOrFail($validated['academic_session_id']);

        if (
            $validated['start_date'] < $session->start_date->format('Y-m-d') ||
            $validated['end_date'] > $session->end_date->format('Y-m-d')
        ) {
            throw ValidationException::withMessages([
                'start_date' => 'The term dates must fall within the selected academic session.',
            ]);
        }

        if (Term::where('academic_session_id', $session->id)->where('name', $validated['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This term already exists in the selected academic session.',
            ]);
        }

        $overlaps = Term::where('academic_session_id', $session->id)
            ->whereDate('start_date', '<=', $validated['end_date'])
            ->whereDate('end_date', '>=', $validated['start_date'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => 'Term dates cannot overlap another term in the same academic session.',
            ]);
        }

        Term::create($validated + ['is_current' => false]);

        return back()->with('success', 'Term created successfully.');
    }

    public function setCurrentSession(Request $request)
    {
        $session = AcademicSession::findOrFail($request->route('session'));

        DB::transaction(function () use ($session): void {
            AcademicSession::query()->update(['is_current' => false]);
            Term::query()->update(['is_current' => false]);
            $session->update(['is_current' => true]);
        });

        return back()->with('success', 'Current academic session updated successfully.');
    }

    public function setCurrentTerm(Request $request)
    {
        $term = Term::with('academicSession')->findOrFail($request->route('term'));

        if (! $term->academicSession || ! $term->academicSession->is_current) {
            throw ValidationException::withMessages([
                'term' => 'Select the academic session as current before making one of its terms current.',
            ]);
        }

        DB::transaction(function () use ($term): void {
            Term::query()->update(['is_current' => false]);
            $term->update(['is_current' => true]);
        });

        return back()->with('success', 'Current academic term updated successfully.');
    }

    public function storeClass(Request $request)
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'name' => ['required', 'string', 'max:100'],
            'level' => ['required', 'integer', 'min:1', 'max:20'],
        ]);
        $duplicate = SchoolClass::where('section_id', $validated['section_id'])
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($validated['name']))])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'A class with this name already exists in the selected section.',
            ]);
        }

        $validated['name'] = trim($validated['name']);
        SchoolClass::create($validated);

        return back()->with('success', 'Class created successfully.');
    }

    public function storeSubject(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);
        Subject::create($validated);

        return back()->with('success', 'Subject created successfully.');
    }
}
