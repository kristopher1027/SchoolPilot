<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use App\Models\Tenant\User;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Subject;
use App\Models\Tenant\TeacherSubject;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    public function index()
    {
        $teachers = Staff::whereHas('user', function($q) {
            $q->whereIn('role', ['class_teacher', 'subject_teacher', 'hod', 'dean']);
        })->with('user')->get();

        $classes = SchoolClass::all();
        $subjects = Subject::all();
        $sessions = AcademicSession::all();
        $terms = Term::all();

        // Load existing assignments
        $assignments = TeacherSubject::with(['subject', 'schoolClass', 'staff.user', 'academicSession', 'term'])
            ->orderByDesc('academic_session_id')
            ->orderBy('class_id')
            ->get();

        return view('tenant.assignments.index', compact('teachers', 'classes', 'subjects', 'sessions', 'terms', 'assignments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
        ]);

        $teacher = Staff::with('user')->findOrFail($data['staff_id']);
        if (! $teacher->user || ! $teacher->user->is_active || ! in_array($teacher->user->role, ['class_teacher', 'subject_teacher', 'hod', 'dean'], true)) {
            throw ValidationException::withMessages([
                'staff_id' => 'Select an active staff member with a teaching role.',
            ]);
        }

        $class = SchoolClass::findOrFail($data['class_id']);
        $subject = Subject::findOrFail($data['subject_id']);
        if ($subject->section_id && (int) $subject->section_id !== (int) $class->section_id) {
            throw ValidationException::withMessages([
                'subject_id' => 'The selected subject does not belong to this class section.',
            ]);
        }

        $term = Term::findOrFail($data['term_id']);
        if ((int) $term->academic_session_id !== (int) $data['session_id']) {
            throw ValidationException::withMessages([
                'term_id' => 'The selected term does not belong to the selected academic session.',
            ]);
        }

        TeacherSubject::firstOrCreate([
            'staff_id' => $data['staff_id'],
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'academic_session_id' => $data['session_id'],
            'term_id' => $data['term_id'],
        ]);

        return back()->with('success', 'Teacher assigned successfully!');
    }
}