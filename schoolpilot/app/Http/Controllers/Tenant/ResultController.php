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
use Illuminate\Support\Facades\Auth;

class ResultController extends Controller
{
    public function index()
    {
        $classes = SchoolClass::all();
        $terms = Term::all();
        $sessions = AcademicSession::all();
        return view('tenant.results.index', compact('classes', 'terms', 'sessions'));
    }

    public function classResults(Request $request)
    {
        if (! $request->filled('class_id')) {
            return redirect('/school/' . $request->route('school') . '/results');
        }

        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
        ]);

        $classId = $validated['class_id'];
        $termId = $validated['term_id'];
        $sessionId = $validated['session_id'];
        $term = Term::whereKey($termId)->where('academic_session_id', $sessionId)->firstOrFail();

        $class = SchoolClass::findOrFail($classId);
        
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
                    ->where('class_id', $classId)
                    ->where('academic_session_id', $sessionId)
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
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
        ]);
        Term::whereKey($validated['term_id'])
            ->where('academic_session_id', $validated['session_id'])
            ->firstOrFail();
        
        $approval = ResultApproval::where('student_id', $request->student_id)
            ->where('term_id', $request->term_id)
            ->where('academic_session_id', $request->session_id)
            ->first();

        if (!$approval) {
            return back()->withErrors(['error' => 'Approval record not found.']);
        }

        $staff = Staff::where('user_id', Auth::id())->first();
        abort_unless($staff, 403, 'Your staff profile is required to approve results.');
        $staffId = $staff->id;
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
        $validated = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
        ]);
        $termId = $validated['term_id'];
        $sessionId = $validated['session_id'];
        Term::whereKey($termId)->where('academic_session_id', $sessionId)->firstOrFail();
        $student = Student::findOrFail($studentId);

        if (Auth::guard('school')->user()?->role === 'parent') {
            $guardian = \App\Models\Tenant\Guardian::where('user_id', Auth::guard('school')->id())->firstOrFail();
            abort_unless($guardian->students()->where('students.id', $student->id)->exists(), 403);
        }

        $class = SchoolClass::findOrFail($student->class_id);

        $approval = ResultApproval::where('student_id', $studentId)
            ->where('term_id', $termId)
            ->where('academic_session_id', $sessionId)
            ->firstOrFail();

        if (Auth::guard('school')->user()?->role === 'parent') {
            abort_unless($approval->status === 'published', 404);
        }

        $subjects = Subject::where('section_id', $class->section_id)->get();
        $gradingBands = GradingBand::whereHas('gradingScale', function($q) use ($class) {
            $q->where('section_id', $class->section_id)->where('is_default', true);
        })->get();

        $subjectScores = [];
        $totalScore = 0;
        foreach ($subjects as $subject) {
            $scores = ScoreEntry::where('student_id', $student->id)
                ->where('class_id', $class->id)
                ->where('subject_id', $subject->id)
                ->where('term_id', $termId)
                ->where('academic_session_id', $sessionId)
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

        $term = Term::find($termId);
        $session = AcademicSession::find($sessionId);

        return view('tenant.results.report_card', compact('student', 'class', 'subjectScores', 'totalScore', 'term', 'session', 'approval'));
    }
}