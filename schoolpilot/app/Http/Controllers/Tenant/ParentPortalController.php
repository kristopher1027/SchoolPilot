<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\ResultApproval;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ParentPortalController extends Controller
{
    public function dashboard()
    {
        $user = Auth::guard('school')->user();
        abort_unless($user && $user->role === 'parent', 403);

        $guardian = Guardian::query()
            ->where('user_id', $user->id)
            ->with(['students' => fn ($query) => $query
                ->with(['section', 'schoolClass'])
                ->orderBy('last_name')
                ->orderBy('first_name')])
            ->firstOrFail();

        $studentIds = $guardian->students->pluck('id');
        $attendance = AttendanceRecord::query()
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($records) => $records->take(5));

        $publishedResults = DB::table('result_approvals as approvals')
            ->join('terms', 'terms.id', '=', 'approvals.term_id')
            ->join('academic_sessions', 'academic_sessions.id', '=', 'approvals.academic_session_id')
            ->whereIn('approvals.student_id', $studentIds)
            ->where('approvals.status', 'published')
            ->orderByDesc('academic_sessions.start_date')
            ->orderByDesc('terms.start_date')
            ->get([
                'approvals.student_id',
                'approvals.term_id',
                'approvals.academic_session_id',
                'terms.name as term_name',
                'academic_sessions.name as session_name',
            ])
            ->groupBy('student_id');

        return view('tenant.parent-portal.dashboard', compact(
            'guardian',
            'attendance',
            'publishedResults'
        ));
    }
}
