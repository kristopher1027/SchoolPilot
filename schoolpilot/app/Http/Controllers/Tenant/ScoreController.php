<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ResultApproval;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Student;
use App\Models\Tenant\ScoreEntry;
use App\Models\Tenant\AssessmentComponent;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use App\Models\Tenant\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    public function index()
    {
        $classes = SchoolClass::all();
        $subjects = Subject::all();
        $sessions = AcademicSession::all();
        $terms = Term::all();

        return view('tenant.scores.index', compact('classes', 'subjects', 'sessions', 'terms'));
    }

    public function entry(Request $request)
    {
        if (! $request->filled('class_id')) {
            return redirect('/school/' . $request->route('school') . '/scores');
        }

        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
        ]);

        $classId = $validated['class_id'];
        $subjectId = $validated['subject_id'];
        $termId = $validated['term_id'];
        $sessionId = $validated['session_id'];

        $class = SchoolClass::findOrFail($classId);
        abort_unless(
            Subject::whereKey($subjectId)->where('section_id', $class->section_id)->exists(),
            422,
            'The selected subject does not belong to this class section.'
        );
        Term::whereKey($termId)->where('academic_session_id', $sessionId)->firstOrFail();
        $staff = Staff::where('user_id', Auth::id())->first();
        abort_unless($staff, 403, 'Your staff profile is required to enter scores.');
        $this->authorizeTeacherAssignment($staff, $classId, $subjectId, $sessionId, $termId);
        $students = Student::where('class_id', $classId)->get();
        $sectionId = $class->section_id;
        $components = AssessmentComponent::where('section_id', $sectionId)
            ->where('type', '!=', 'observation')
            ->where(function ($query) use ($subjectId) {
                $query->whereNull('subject_id')->orWhere('subject_id', $subjectId);
            })
            ->get();

        $existingScores = ScoreEntry::where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->where('academic_session_id', $sessionId)
            ->get()
            ->groupBy('student_id');

        // Check if submitted (any student has a non-draft status)
        $approval = ResultApproval::where('class_id', $classId)
            ->where('term_id', $termId)
            ->where('academic_session_id', $sessionId)
            ->where('status', '!=', 'draft')
            ->first();

        return view('tenant.scores.entry', compact('students', 'components', 'existingScores', 'classId', 'subjectId', 'termId', 'sessionId', 'approval'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'scores' => ['required', 'array'],
        ]);

        $class = SchoolClass::findOrFail($validated['class_id']);
        abort_unless(Subject::whereKey($validated['subject_id'])->where('section_id', $class->section_id)->exists(), 422, 'The selected subject does not belong to this class section.');
        Term::whereKey($validated['term_id'])->where('academic_session_id', $validated['session_id'])->firstOrFail();
        $staff = Staff::where('user_id', Auth::id())->first();
        abort_unless($staff, 403, 'Your staff profile is required to enter scores.');
        $this->authorizeTeacherAssignment($staff, $validated['class_id'], $validated['subject_id'], $validated['session_id'], $validated['term_id']);
        $staffId = $staff->id;

        foreach ($request->scores as $studentId => $componentScores) {
            abort_unless(ctype_digit((string) $studentId) && Student::whereKey($studentId)->where('class_id', $class->id)->exists(), 422, 'A submitted student does not belong to this class.');
            abort_unless(is_array($componentScores), 422, 'Invalid score data.');
            foreach ($componentScores as $componentId => $score) {
                abort_unless(ctype_digit((string) $componentId) && AssessmentComponent::whereKey($componentId)
                    ->where('section_id', $class->section_id)
                    ->where('type', '!=', 'observation')
                    ->where(function ($query) use ($validated) {
                        $query->whereNull('subject_id')->orWhere('subject_id', $validated['subject_id']);
                    })
                    ->exists(), 422, 'An assessment component does not belong to this class section.');
                if ($score !== null && $score !== '') {
                    $component = AssessmentComponent::findOrFail($componentId);
                    abort_unless(is_numeric($score) && $score >= 0 && $score <= $component->max_score, 422, 'A score is outside the allowed range.');
                }
            }
        }

        

        foreach ($request->scores as $studentId => $componentScores) {
            foreach ($componentScores as $componentId => $score) {
                if ($score !== null && $score !== '') {
                    ScoreEntry::updateOrCreate(
                        [
                            'student_id' => $studentId,
                            'subject_id' => $request->subject_id,
                            'term_id' => $validated['term_id'],
                            'academic_session_id' => $validated['session_id'],
                            'assessment_component_id' => $componentId,
                        ],
                        [
                            'class_id' => $validated['class_id'],
                            'academic_session_id' => $validated['session_id'],
                            'score' => $score,
                            'entered_by' => $staffId,
                        ]
                    );
                }
            }
        }

        return back()->with('success', 'Scores saved as Draft. You can submit for approval when ready.');
    }

    public function submitForApproval(Request $request)
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
        ]);
        Term::whereKey($validated['term_id'])->where('academic_session_id', $validated['session_id'])->firstOrFail();
        $students = Student::where('class_id', $validated['class_id'])->get();
        
        if ($students->isEmpty()) {
            return back()->withErrors(['error' => 'No students found in this class.']);
        }

        $staff = Staff::where('user_id', Auth::id())->first();
        abort_unless($staff, 403, 'Your staff profile is required to submit scores.');
        $role = Auth::user()->role;
        if ($role === 'class_teacher') {
            $this->authorizeTeacherAssignment($staff, $validated['class_id'], null, $validated['session_id'], $validated['term_id']);
        } else {
            abort_unless(in_array($role, ['school_admin', 'principal', 'vice_principal', 'dean', 'hod'], true), 403, 'Only the assigned class teacher or an authorised school leader can submit class results.');
        }
        $staffId = $staff->id;

        foreach ($students as $student) {
            // Check if this student has any scores entered
            $hasScores = ScoreEntry::where('student_id', $student->id)
                ->where('class_id', $validated['class_id'])
                ->where('term_id', $validated['term_id'])
                ->where('academic_session_id', $validated['session_id'])
                ->exists();

            if (!$hasScores) {
                continue; // Skip students with no scores
            }

            ResultApproval::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'term_id' => $validated['term_id'],
                    'academic_session_id' => $validated['session_id'],
                ],
                [
                    'class_id' => $request->class_id,
                    'status' => 'class_teacher_pending',
                    'submitted_by' => $staffId,
                ]
            );
        }

        return back()->with('success', 'Scores submitted for approval!');
    }
    private function authorizeTeacherAssignment(Staff $staff, int $classId, ?int $subjectId, int $sessionId, int $termId): void
    {
        $role = Auth::user()->role;

        if ($role === 'class_teacher') {
            $assigned = DB::table('class_teachers')
                ->where('staff_id', $staff->id)
                ->where('class_id', $classId)
                ->where('academic_session_id', $sessionId)
                ->exists();

            abort_unless($assigned, 403, 'You are not assigned as the teacher for this class and academic session.');
            return;
        }

        if ($role === 'subject_teacher') {
            $assigned = $subjectId !== null && DB::table('teacher_subjects')
                ->where('staff_id', $staff->id)
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('academic_session_id', $sessionId)
                ->where('term_id', $termId)
                ->exists();

            abort_unless($assigned, 403, 'You are not assigned to teach this subject in the selected class, term and session.');
        }
    }

}
