<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Staff;
use App\Models\Tenant\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'date' => ['nullable', 'date'],
        ]);

        $user = Auth::guard('school')->user();
        $classesQuery = SchoolClass::query()->with('section')->orderBy('name');

        if ($user->role === 'class_teacher') {
            $staff = Staff::where('user_id', $user->id)->first();
            $assignedClassIds = $staff
                ? DB::table('class_teachers')
                    ->where('staff_id', $staff->id)
                    ->pluck('class_id')
                : collect();

            $classesQuery->whereIn('id', $assignedClassIds);
        }

        $classes = $classesQuery->get();
        $selectedClassId = $request->input('class_id');

        if (! $selectedClassId && $classes->isNotEmpty()) {
            $selectedClassId = $classes->first()->id;
        }

        $selectedClass = $classes->firstWhere('id', (int) $selectedClassId);
        $date = $request->input('date', now()->toDateString());
        $students = collect();
        $records = collect();

        if ($selectedClass) {
            $students = Student::where('class_id', $selectedClass->id)
                ->where('status', 'active')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            $records = AttendanceRecord::where('class_id', $selectedClass->id)
                ->whereDate('date', $date)
                ->get()
                ->keyBy('student_id');
        }

        $summary = [
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
        ];

        return view('tenant.attendance.index', compact(
            'classes', 'selectedClass', 'selectedClassId', 'date',
            'students', 'records', 'summary'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'notes' => ['sometimes', 'array'],
            'notes.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = Auth::guard('school')->user();
        $class = SchoolClass::findOrFail($data['class_id']);

        if ($user->role === 'class_teacher') {
            $staff = Staff::where('user_id', $user->id)->first();
            $isAssigned = $staff && DB::table('class_teachers')
                ->where('staff_id', $staff->id)
                ->where('class_id', $class->id)
                ->exists();

            abort_unless($isAssigned, 403);
        }

        $students = Student::where('class_id', $class->id)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $submittedIds = array_map('strval', array_keys($data['statuses']));
        sort($students);
        sort($submittedIds);

        if ($students !== $submittedIds) {
            return back()
                ->withErrors(['statuses' => 'Attendance must be submitted for every active student in the selected class.'])
                ->withInput();
        }

        $staffId = Staff::where('user_id', $user->id)->value('id');

        DB::transaction(function () use ($data, $staffId, $class) {
            foreach ($data['statuses'] as $studentId => $status) {
                AttendanceRecord::updateOrCreate(
                    ['student_id' => $studentId, 'date' => $data['date']],
                    [
                        'class_id' => $class->id,
                        'status' => $status,
                        'recorded_by' => $staffId,
                        'notes' => $data['notes'][$studentId] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('attendance.index', [
            'school' => $request->route('school'),
            'class_id' => $class->id,
            'date' => $data['date'],
        ])->with('success', 'Attendance saved successfully.');
    }
}
