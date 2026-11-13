<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Announcement;
use Illuminate\Http\Request;
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

        $announcements = Announcement::query()
            ->visibleTo($user)
            ->with('author:id,name')
            ->latest('published_at')
            ->limit(10)
            ->get();

        $announcementReads = DB::table('announcement_reads')
            ->where('user_id', $user->id)
            ->whereIn('announcement_id', $announcements->pluck('id'))
            ->pluck('read_at', 'announcement_id');

        $studentIds = $guardian->students->pluck('id');
        $notifications = DB::table('parent_notifications')
            ->where('user_id', $user->id)
            ->whereIn('student_id', $studentIds)
            ->latest()
            ->limit(20)
            ->get();
        $unreadNotificationCount = $notifications->whereNull('read_at')->count();
        $attendance = AttendanceRecord::query()
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($records) => $records->take(5));

        $invoices = DB::table('invoices as invoices')
            ->join('academic_sessions', 'academic_sessions.id', '=', 'invoices.academic_session_id')
            ->join('terms', 'terms.id', '=', 'invoices.term_id')
            ->whereIn('invoices.student_id', $studentIds)
            ->orderByDesc('academic_sessions.start_date')
            ->orderByDesc('terms.start_date')
            ->orderByDesc('invoices.id')
            ->get([
                'invoices.id',
                'invoices.student_id',
                'invoices.total_amount',
                'invoices.amount_paid',
                'invoices.balance',
                'invoices.status',
                'invoices.due_date',
                'academic_sessions.name as session_name',
                'terms.name as term_name',
            ])
            ->groupBy('student_id');

        $invoiceIds = $invoices->flatten(1)->pluck('id');
        $refundTotals = DB::table('payment_refunds')
            ->select('payment_id')
            ->selectRaw('SUM(amount) as refunded_amount')
            ->whereIn('status', ['approved', 'settled'])
            ->groupBy('payment_id');

        $payments = DB::table('payments as payments')
            ->join('invoices as invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoinSub($refundTotals, 'refund_totals', function ($join) {
                $join->on('refund_totals.payment_id', '=', 'payments.id');
            })
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->orderByDesc('payments.paid_at')
            ->get([
                'payments.id',
                'payments.invoice_id',
                'payments.amount',
                'payments.method',
                'payments.receipt_no',
                'payments.paid_at',
                DB::raw('COALESCE(refund_totals.refunded_amount, 0) as refunded_amount'),
            ])
            ->groupBy('invoice_id');

        $refunds = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->whereIn('payment_refunds.status', ['approved', 'settled'])
            ->orderByDesc('payment_refunds.created_at')
            ->get([
                'payments.invoice_id',
                'payment_refunds.amount',
                'payment_refunds.status',
                'payment_refunds.settled_at',
            ])
            ->groupBy('invoice_id');

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
            'publishedResults',
            'invoices',
            'payments',
            'refunds',
            'announcements',
            'announcementReads',
            'notifications',
            'unreadNotificationCount'
        ));
    }

    public function markAnnouncementRead(Request $request, int $announcement)
    {
        $user = Auth::guard('school')->user();
        abort_unless($user && $user->role === 'parent', 403);

        $record = Announcement::query()
            ->visibleTo($user)
            ->whereKey($announcement)
            ->firstOrFail();

        DB::table('announcement_reads')->insertOrIgnore([
            'announcement_id' => $record->id,
            'user_id' => $user->id,
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect('/school/' . $request->route('school') . '/parent-portal')
            ->with('success', 'Announcement marked as read.');
    }
    public function markNotificationRead(Request $request, int $notification)
    {
        $user = Auth::guard('school')->user();
        abort_unless($user && $user->role === 'parent', 403);

        $guardian = Guardian::where('user_id', $user->id)->firstOrFail();
        $studentIds = $guardian->students()->pluck('students.id');

        $updated = DB::table('parent_notifications')
            ->where('id', $notification)
            ->where('user_id', $user->id)
            ->whereIn('student_id', $studentIds)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        abort_unless($updated === 1 || DB::table('parent_notifications')
            ->where('id', $notification)
            ->where('user_id', $user->id)
            ->whereIn('student_id', $studentIds)
            ->exists(), 404);

        return redirect('/school/' . $request->route('school') . '/parent-portal')
            ->with('success', 'Notification marked as read.');
    }

}
