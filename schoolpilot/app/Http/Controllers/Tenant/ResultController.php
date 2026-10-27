<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ResultApproval;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Student;
use App\Models\Tenant\ScoreEntry;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use App\Models\Tenant\GradingBand;
use App\Models\Tenant\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ResultController extends Controller
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
        $terms = Term::all();
        $sessions = AcademicSession::all();
        return view('tenant.results.index', compact('classes', 'terms', 'sessions'));
    }

    public function classResults(Request $request)
    {
        if (!$request->has('class_id')) {
            return redirect('/school/greenfield/results');
        }

        $classId = $request->class_id;
        $termId = $request->term_id;
        $sessionId = $request->session_id;

        $class = SchoolClass::find($classId);
        
        // ONLY get students whose results have been submitted for compilation
        $students = Student::where('class_id', $classId)
            ->whereHas('resultApprovals', function($q) use ($termId, $sessionId) {
                $q->where('term_id', $termId)->where('academic_session_id', $sessionId)->whereIn('status', ['class_teacher_pending', 'hod_pending', 'dean_pending', 'principal_pending', 'published']);
            })->get();

        $subjects = Subject::where('section_id', $class->section_id)->get();
        $gradingBands = GradingBand::whereHas('gradingScale', function($q) use ($class) {
            $q->where('section_id', $class->section_id)->where('is_default', true);
        })->get();

        $results = [];
        foreach ($students as $student) {
            $totalScore = 0;
            $subjectScores = [];

            foreach ($subjects as $subject) {
                $scores = ScoreEntry::where('student_id', $student->id)
                    ->where('subject_id', $subject->id)
                    ->where('term_id', $termId)
                    ->get();

                $subjectTotal = $scores->sum('score');
                $totalScore += $subjectTotal;

                $grade = 'F9'; $remark = 'Fail';
                foreach ($gradingBands as $band) {
                    if ($subjectTotal >= $band->min_score && $subjectTotal <= $band->max_score) {
                        $grade = $band->grade; $remark = $band->remark; break;
                    }
                }
                $subjectScores[] = ['subject' => $subject->name, 'total' => $subjectTotal, 'grade' => $grade, 'remark' => $remark];
            }

            $results[] = [
                'student' => $student,
                'subjects' => $subjectScores,
                'total' => $totalScore,
                'average' => count($subjects) > 0 ? round($totalScore / count($subjects), 2) : 0,
            ];
        }

        usort($results, function($a, $b) { return $b['total'] <=> $a['total']; });

        $position = 1;
        foreach ($results as $index => &$result) {
            if ($index > 0 && $result['total'] < $results[$index - 1]['total']) { $position = $index + 1; }
            $result['position'] = $position;
        }

        return view('tenant.results.class', compact('results', 'class', 'termId', 'sessionId'));
    }

    public function approve(Request $request)
    {
        $request->validate(['student_id' => 'required', 'term_id' => 'required', 'session_id' => 'required']);
        
        $approval = ResultApproval::where('student_id', $request->student_id)
            ->where('term_id', $request->term_id)
            ->where('academic_session_id', $request->session_id)
            ->first();

        if (!$approval) {
            return back()->withErrors(['error' => 'Approval record not found.']);
        }

        $staff = Staff::where('user_id', Auth::id())->first();
        $staffId = $staff ? $staff->id : 1;
        $role = Auth::user()->role;

        if ($role === 'class_teacher' && $approval->status === 'class_teacher_pending') {
            $approval->update(['status' => 'hod_pending', 'class_teacher_approved_by' => $staffId]);
            return back()->with('success', 'Approved as Class Teacher. Sent to HOD.');
        }

        if ($role === 'hod' && $approval->status === 'hod_pending') {
            $approval->update(['status' => 'dean_pending', 'hod_approved_by' => $staffId]);
            return back()->with('success', 'Approved as HOD. Sent to Dean.');
        }

        if ($role === 'dean' && $approval->status === 'dean_pending') {
            $approval->update(['status' => 'principal_pending', 'dean_approved_by' => $staffId]);
            return back()->with('success', 'Approved as Dean. Sent to Principal.');
        }

        if (in_array($role, ['principal', 'vice_principal', 'school_admin']) && in_array($approval->status, ['principal_pending', 'dean_pending', 'hod_pending'])) {
            $approval->update(['status' => 'published', 'published_by' => $staffId]);
            return back()->with('success', 'Results Published! Parents can now view them.');
        }

        return back()->withErrors(['error' => "Cannot approve at current stage. Your role: $role. Status: {$approval->status}"]);
    }

    public function reportCard(Request $request, $studentId)
    {
        $termId = $request->term_id;
        $sessionId = $request->session_id;
        $student = Student::find($studentId);
        $class = SchoolClass::find($student->class_id);
        
        $approval = ResultApproval::where('student_id', $studentId)
            ->where('term_id', $termId)
            ->where('academic_session_id', $sessionId)
            ->first();

        $subjects = Subject::where('section_id', $class->section_id)->get();
        $gradingBands = GradingBand::whereHas('gradingScale', function($q) use ($class) {
            $q->where('section_id', $class->section_id)->where('is_default', true);
        })->get();

        $subjectScores = [];
        $totalScore = 0;
        foreach ($subjects as $subject) {
            $scores = ScoreEntry::where('student_id', $student->id)->where('subject_id', $subject->id)->where('term_id', $termId)->get();
            $subjectTotal = $scores->sum('score');
            $totalScore += $subjectTotal;

            $grade = 'F9'; $remark = 'Fail';
            foreach ($gradingBands as $band) {
                if ($subjectTotal >= $band->min_score && $subjectTotal <= $band->max_score) {
                    $grade = $band->grade; $remark = $band->remark; break;
                }
            }
            $subjectScores[] = ['subject' => $subject->name, 'total' => $subjectTotal, 'grade' => $grade, 'remark' => $remark];
        }

        $term = Term::find($termId);
        $session = AcademicSession::find($sessionId);

        return view('tenant.results.report_card', compact('student', 'class', 'subjectScores', 'totalScore', 'term', 'session', 'approval'));
    }
}