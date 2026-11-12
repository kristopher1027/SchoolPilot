<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Staff;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $query = TimetableEntry::with(['schoolClass.section', 'subject', 'staff.user', 'academicSession', 'term'])
            ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END")
            ->orderBy('start_time');

        foreach (['class_id', 'academic_session_id', 'term_id', 'day_of_week'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $entries = $query->paginate(30)->withQueryString();
        $classes = SchoolClass::with('section')->orderBy('name')->get();
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $terms = Term::with('academicSession')->orderBy('start_date')->get();

        return view('tenant.timetable.index', compact('entries', 'classes', 'sessions', 'terms'));
    }

    public function create()
    {
        $classes = SchoolClass::with('section')->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $staff = Staff::with('user')->whereHas('user', fn ($q) => $q->where('is_active', true))->get();
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $terms = Term::with('academicSession')->orderBy('start_date')->get();
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return view('tenant.timetable.create', compact('classes', 'subjects', 'staff', 'sessions', 'terms', 'days'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'day_of_week' => ['required', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:80'],
        ]);

        $term = Term::findOrFail($data['term_id']);
        if ((int) $term->academic_session_id !== (int) $data['academic_session_id']) {
            throw ValidationException::withMessages(['term_id' => 'The selected term does not belong to the selected academic session.']);
        }

        $class = SchoolClass::findOrFail($data['class_id']);
        $subject = Subject::findOrFail($data['subject_id']);
        if ($subject->section_id && (int) $subject->section_id !== (int) $class->section_id) {
            throw ValidationException::withMessages(['subject_id' => 'The subject does not belong to the selected class section.']);
        }

        $assignment = DB::table('teacher_subjects')
            ->where('staff_id', $data['staff_id'])
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('academic_session_id', $data['academic_session_id'])
            ->where('term_id', $data['term_id'])
            ->exists();

        if (! $assignment) {
            throw ValidationException::withMessages(['staff_id' => 'Assign this teacher to the selected class, subject, session and term before scheduling the lesson.']);
        }

        $overlap = fn ($q) => $q->where('academic_session_id', $data['academic_session_id'])
            ->where('term_id', $data['term_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time']);

        $classBusy = TimetableEntry::where('class_id', $data['class_id'])->where($overlap)->exists();
        if ($classBusy) {
            throw ValidationException::withMessages(['start_time' => 'This class already has a lesson during that time.']);
        }

        $teacherBusy = TimetableEntry::where('staff_id', $data['staff_id'])->where($overlap)->exists();
        if ($teacherBusy) {
            throw ValidationException::withMessages(['staff_id' => 'This teacher is already scheduled during that time.']);
        }

        if (! empty($data['room'])) {
            $roomBusy = TimetableEntry::where('room', $data['room'])->where($overlap)->exists();
            if ($roomBusy) {
                throw ValidationException::withMessages(['room' => 'This room is already booked during that time.']);
            }
        }

        TimetableEntry::create($data);

        return redirect('/school/' . $request->route('school') . '/timetable')->with('success', 'Lesson added to the timetable.');
    }

    public function destroy(Request $request, TimetableEntry $entry)
    {
        $entry->delete();

        return redirect('/school/' . $request->route('school') . '/timetable')->with('success', 'Timetable lesson removed.');
    }
}
