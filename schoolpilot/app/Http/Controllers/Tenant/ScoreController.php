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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ScoreController extends Controller
{
    public function __construct()
    {
        Config::set('database.connections.tenant_sqlite.database', database_path('tenants/greenfield.sqlite'));
        DB::purge('tenant_sqlite');
        Config::set('database.default', 'tenant_sqlite');
    }

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
        if (!$request->has('class_id')) {
            return redirect('/school/greenfield/scores');
        }

        $classId = $request->class_id;
        $subjectId = $request->subject_id;
        $termId = $request->term_id;
        $sessionId = $request->session_id;

        $students = Student::where('class_id', $classId)->get();
        $sectionId = SchoolClass::find($classId)->section_id;
        $components = AssessmentComponent::where('section_id', $sectionId)->where('type', '!=', 'observation')->get();

        $existingScores = ScoreEntry::where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
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
        $request->validate([
            'class_id' => 'required',
            'term_id' => 'required',
            'session_id' => 'required',
            'scores' => 'required|array'
        ]);

        $staff = Staff::where('user_id', Auth::id())->first();
        $staffId = $staff ? $staff->id : 1;

        foreach ($request->scores as $studentId => $componentScores) {
            foreach ($componentScores as $componentId => $score) {
                if ($score !== null && $score !== '') {
                    ScoreEntry::updateOrCreate(
                        [
                            'student_id' => $studentId,
                            'subject_id' => $request->subject_id,
                            'term_id' => $request->term_id,
                            'assessment_component_id' => $componentId,
                        ],
                        [
                            'class_id' => $request->class_id,
                            'academic_session_id' => $request->session_id,
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
        $request->validate([
            'class_id' => 'required',
            'term_id' => 'required',
            'session_id' => 'required',
        ]);

        $students = Student::where('class_id', $request->class_id)->get();
        
        if ($students->isEmpty()) {
            return back()->withErrors(['error' => 'No students found in this class.']);
        }

        $staff = Staff::where('user_id', Auth::id())->first();
        $staffId = $staff ? $staff->id : 1;

        foreach ($students as $student) {
            // Check if this student has any scores entered
            $hasScores = ScoreEntry::where('student_id', $student->id)
                ->where('term_id', $request->term_id)
                ->exists();

            if (!$hasScores) {
                continue; // Skip students with no scores
            }

            ResultApproval::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'term_id' => $request->term_id,
                    'academic_session_id' => $request->session_id,
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
}