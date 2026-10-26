<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Student;
use App\Models\Tenant\ScoreEntry;
use App\Models\Tenant\AssessmentComponent;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    public function __construct()
    {
        // Force the controller to use the greenfield database
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
        // If there is no data, send back to the selection page
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

        return view('tenant.scores.entry', compact('students', 'components', 'existingScores', 'classId', 'subjectId', 'termId', 'sessionId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_id' => 'required',
            'subject_id' => 'required',
            'term_id' => 'required',
            'session_id' => 'required',
            'scores' => 'required|array'
        ]);

        $staff = \App\Models\Tenant\Staff::where('user_id', \Illuminate\Support\Facades\Auth::id())->first();
        
        // If the logged-in user is an admin and not a staff, create a dummy staff ID or use 1
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

        return back()->with('success', 'Scores saved successfully!');
    }
}