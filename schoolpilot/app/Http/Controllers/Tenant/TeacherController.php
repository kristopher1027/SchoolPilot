<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use App\Models\Tenant\TeacherSubject;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TeacherController extends Controller
{
    public function __construct()
    {
        Config::set('database.connections.tenant_sqlite.database', database_path('tenants/greenfield.sqlite'));
        DB::purge('tenant_sqlite');
        Config::set('database.default', 'tenant_sqlite');
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
        $students = Student::where('class_id', $classId)->get();
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

                $subjectScores[] = [
                    'subject' => $subject->name,
                    'total' => $subjectTotal,
                    'grade' => $grade,
                    'remark' => $remark,
                ];
            }

            $results[] = [
                'student' => $student,
                'subjects' => $subjectScores,
                'total' => $totalScore,
                'average' => count($subjects) > 0 ? round($totalScore / count($subjects), 2) : 0,
            ];
        }

        // Sort by total score descending
        usort($results, function($a, $b) { return $b['total'] <=> $a['total']; });

        // Assign positions
        $position = 1;
        foreach ($results as $index => &$result) {
            if ($index > 0 && $result['total'] < $results[$index - 1]['total']) {
                $position = $index + 1;
            }
            $result['position'] = $position;

            // Create or update approval record
            ResultApproval::updateOrCreate(
                ['student_id' => $result['student']->id, 'term_id' => $termId, 'academic_session_id' => $sessionId],
                ['class_id' => $classId, 'status' => 'class_teacher_pending']
            );
        }

        return view('tenant.results.class', compact('results', 'class', 'termId', 'sessionId'));
    }

    public function dashboard()
    {
        $staff = Staff::where('user_id', Auth::id())->first();

        if (!$staff) {
            return view('tenant.teacher.dashboard', ['assignments' => collect()]);
        }

        $assignments = TeacherSubject::where('staff_id', $staff->id)
            ->with(['schoolClass', 'subject'])
            ->get();

        return view('tenant.teacher.dashboard', compact('staff', 'assignments'));
    }
}