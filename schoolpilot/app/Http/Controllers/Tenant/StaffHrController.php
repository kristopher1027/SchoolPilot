<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffHrController extends Controller
{
    private const MANAGERS = ['school_admin', 'principal'];

    public function attendanceIndex(Request $request)
    {
        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = $data['date'] ?? now()->toDateString();
        $staff = Staff::with('user')->whereHas('user', fn ($q) => $q->where('is_active', true))->orderBy('staff_no')->get();
        $records = DB::table('staff_attendance_records')->where('attendance_date', $date)->get()->keyBy('staff_id');
        $summary = [
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'on_leave' => $records->where('status', 'on_leave')->count(),
            'unmarked' => max(0, $staff->count() - $records->count()),
        ];

        return view('tenant.hr.attendance', compact('staff', 'records', 'date', 'summary'));
    }

    public function recordAttendance(Request $request)
    {
        $data = $request->validate([
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', Rule::in(['present', 'absent', 'late', 'on_leave'])],
            'check_in' => ['sometimes', 'array'],
            'check_in.*' => ['nullable', 'date_format:H:i'],
            'check_out' => ['sometimes', 'array'],
            'check_out.*' => ['nullable', 'date_format:H:i'],
            'notes' => ['sometimes', 'array'],
            'notes.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $staffIds = Staff::whereHas('user', fn ($q) => $q->where('is_active', true))->pluck('id')->map(fn ($id) => (string) $id)->all();
        $submittedIds = array_map('strval', array_keys($data['statuses']));
        sort($staffIds);
        sort($submittedIds);
        if ($staffIds !== $submittedIds) {
            return back()->withErrors(['statuses' => 'Attendance must be recorded for every active staff member.'])->withInput();
        }

        $actor = Auth::guard('school')->user();
        DB::transaction(function () use ($data, $actor) {
            foreach ($data['statuses'] as $staffId => $status) {
                DB::table('staff_attendance_records')->updateOrInsert(
                    ['staff_id' => $staffId, 'attendance_date' => $data['attendance_date']],
                    [
                        'status' => $status,
                        'check_in' => $data['check_in'][$staffId] ?? null,
                        'check_out' => $data['check_out'][$staffId] ?? null,
                        'notes' => $data['notes'][$staffId] ?? null,
                        'recorded_by' => $actor->id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
            $this->audit($actor->id, 'staff_attendance.recorded', 'staff_attendance', null, null, [
                'date' => $data['attendance_date'], 'staff_count' => count($data['statuses']),
            ], request()->ip());
        });

        return redirect($this->schoolPath($request, '/hr/staff-attendance') . '?date=' . $data['attendance_date'])
            ->with('success', 'Staff attendance saved successfully.');
    }

    public function leaveIndex(Request $request)
    {
        $user = Auth::guard('school')->user();
        $isManager = in_array($user->role, self::MANAGERS, true);
        $staffProfile = Staff::where('user_id', $user->id)->first();

        if (! $isManager && ! $staffProfile) {
            abort(403, 'A staff profile is required to use the leave portal.');
        }

        $types = DB::table('staff_leave_types')->where('is_active', true)->orderBy('name')->get();
        $requests = DB::table('staff_leave_requests as lr')
            ->join('staff as s', 's.id', '=', 'lr.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('staff_leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
            ->select('lr.*', 's.staff_no', 'u.name as staff_name', 'lt.name as leave_type_name', 'lt.is_paid')
            ->when(! $isManager, fn ($q) => $q->where('lr.staff_id', $staffProfile->id))
            ->orderByDesc('lr.created_at')->limit(200)->get();

        $balances = collect();
        if ($staffProfile) {
            $year = now()->year;
            $balances = $types->map(function ($type) use ($staffProfile, $year) {
                $used = DB::table('staff_leave_requests')
                    ->where('staff_id', $staffProfile->id)
                    ->where('leave_type_id', $type->id)
                    ->where('status', 'approved')
                    ->whereYear('start_date', $year)
                    ->sum('days_requested');
                return (object) [
                    'name' => $type->name,
                    'is_paid' => $type->is_paid,
                    'entitled' => (int) $type->annual_days,
                    'used' => (int) $used,
                    'remaining' => max(0, (int) $type->annual_days - (int) $used),
                ];
            });
        }

        return view('tenant.hr.leave', compact('types', 'requests', 'balances', 'isManager', 'staffProfile'));
    }

    public function storeLeaveType(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:staff_leave_types,name'],
            'annual_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['required', 'boolean'],
            'requires_approval' => ['required', 'boolean'],
        ]);

        $id = DB::table('staff_leave_types')->insertGetId([
            'name' => $data['name'],
            'annual_days' => $data['annual_days'],
            'is_paid' => $data['is_paid'],
            'requires_approval' => $data['requires_approval'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit(Auth::guard('school')->id(), 'staff_leave_type.created', 'staff_leave_type', $id, null, $data, $request->ip());

        return back()->with('success', 'Leave type created successfully.');
    }

    public function applyLeave(Request $request)
    {
        $user = Auth::guard('school')->user();
        $staff = Staff::where('user_id', $user->id)->firstOrFail();
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:staff_leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:3000'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        if ($start->year !== $end->year) {
            return back()->withErrors(['end_date' => 'A leave request must fall within one calendar year.'])->withInput();
        }

        $days = $this->workingDays($start, $end);
        if ($days < 1) {
            return back()->withErrors(['end_date' => 'The selected period contains no working days.'])->withInput();
        }

        $type = DB::table('staff_leave_types')->where('id', $data['leave_type_id'])->where('is_active', true)->first();
        abort_unless($type, 404);

        $overlap = DB::table('staff_leave_requests')->where('staff_id', $staff->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])->exists();
        if ($overlap) {
            return back()->withErrors(['start_date' => 'This request overlaps an existing pending or approved leave request.'])->withInput();
        }

        if ($type->is_paid) {
            $used = DB::table('staff_leave_requests')->where('staff_id', $staff->id)
                ->where('leave_type_id', $type->id)->where('status', 'approved')
                ->whereYear('start_date', $start->year)->sum('days_requested');
            if ($used + $days > $type->annual_days) {
                return back()->withErrors(['leave_type_id' => 'This request exceeds the remaining paid leave balance.'])->withInput();
            }
        }

        $status = $type->requires_approval ? 'pending' : 'approved';
        $id = DB::table('staff_leave_requests')->insertGetId([
            'staff_id' => $staff->id,
            'leave_type_id' => $type->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_requested' => $days,
            'reason' => $data['reason'],
            'status' => $status,
            'reviewed_by' => $status === 'approved' ? $user->id : null,
            'reviewed_at' => $status === 'approved' ? now() : null,
            'review_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($user->id, 'staff_leave.applied', 'staff_leave_request', $id, null, [
            'status' => $status, 'days' => $days, 'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
        ], $request->ip());

        return back()->with('success', $status === 'approved' ? 'Leave recorded successfully.' : 'Leave application submitted for approval.');
    }

    public function reviewLeave(Request $request, int $leave)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $actor = Auth::guard('school')->user();

        DB::transaction(function () use ($leave, $data, $actor, $request) {
            $application = DB::table('staff_leave_requests')->where('id', $leave)->lockForUpdate()->first();
            abort_unless($application, 404);
            abort_unless($application->status === 'pending', 422, 'Only pending leave requests can be reviewed.');

            if ($data['decision'] === 'approved') {
                $type = DB::table('staff_leave_types')->where('id', $application->leave_type_id)->first();
                if ($type && $type->is_paid) {
                    $used = DB::table('staff_leave_requests')->where('staff_id', $application->staff_id)
                        ->where('leave_type_id', $application->leave_type_id)->where('status', 'approved')
                        ->whereYear('start_date', Carbon::parse($application->start_date)->year)->sum('days_requested');
                    abort_if($used + $application->days_requested > $type->annual_days, 422, 'Approving this request would exceed the leave allowance.');
                }
                $conflict = DB::table('staff_leave_requests')->where('staff_id', $application->staff_id)
                    ->where('id', '!=', $application->id)->where('status', 'approved')
                    ->whereDate('start_date', '<=', $application->end_date)
                    ->whereDate('end_date', '>=', $application->start_date)->exists();
                abort_if($conflict, 422, 'This request now overlaps another approved leave.');
            }

            DB::table('staff_leave_requests')->where('id', $application->id)->update([
                'status' => $data['decision'],
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
                'updated_at' => now(),
            ]);
            $this->audit($actor->id, 'staff_leave.' . $data['decision'], 'staff_leave_request', $application->id,
                ['status' => 'pending'], ['status' => $data['decision'], 'note' => $data['review_note'] ?? null], $request->ip());
        });

        return back()->with('success', 'Leave request ' . $data['decision'] . '.');
    }

    private function workingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (! $date->isWeekend()) $days++;
        }
        return $days;
    }

    private function audit(?int $userId, string $action, string $entityType, ?int $entityId, mixed $oldValue, mixed $newValue, ?string $ip): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $oldValue === null ? null : json_encode($oldValue),
            'new_value' => $newValue === null ? null : json_encode($newValue),
            'ip_address' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function schoolPath(Request $request, string $path): string
    {
        return '/school/' . $request->route('school') . $path;
    }
}
