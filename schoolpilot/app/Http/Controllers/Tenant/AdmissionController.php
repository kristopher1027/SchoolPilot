<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AdmissionApplication;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Section;
use App\Models\Tenant\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdmissionController extends Controller
{
    public function apply()
    {
        $sections = Section::query()->orderBy('name')->get();

        return view('tenant.admissions.apply', compact('sections'));
    }

    public function submitApplication(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_phone' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:5000'],
            'previous_school' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($data['class_id']) && ! SchoolClass::query()
            ->whereKey($data['class_id'])->where('section_id', $data['section_id'])->exists()) {
            return back()->withErrors(['class_id' => 'The selected class does not belong to the selected section.'])->withInput();
        }

        $application = DB::transaction(function () use ($data) {
            $application = AdmissionApplication::create($data + [
                'application_no' => 'PENDING-' . str()->uuid(),
                'status' => 'submitted',
            ]);
            $application->update([
                'application_no' => 'ADM-' . now()->format('Y') . '-' . str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $application;
        });

        return redirect('/school/' . $request->route('school') . '/admissions/apply')
            ->with('application_number', $application->application_no);
    }

    public function index(Request $request)
    {
        $query = AdmissionApplication::with(['section', 'schoolClass']);
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('application_no', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('guardian_name', 'like', "%{$search}%")
                    ->orWhere('guardian_phone', 'like', "%{$search}%");
            });
        }
        $applications = $query->latest()->paginate(20)->withQueryString();

        return view('tenant.admissions.index', compact('applications'));
    }

    public function show(AdmissionApplication $application)
    {
        $application->load(['section', 'schoolClass', 'reviewer', 'student']);

        return view('tenant.admissions.show', compact('application'));
    }

    public function review(Request $request, AdmissionApplication $application)
    {
        if (in_array($application->status, ['converted', 'rejected'], true)) {
            return back()->withErrors(['status' => 'This application can no longer be reviewed.']);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'accepted', 'waitlisted', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $application->update($data + [
            'reviewed_by' => Auth::guard('school')->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Application review saved.');
    }

    public function convert(Request $request, AdmissionApplication $application)
    {
        if ($application->status !== 'accepted' || $application->student_id) {
            return back()->withErrors(['application' => 'Only accepted applications that have not already been converted can be enrolled.']);
        }

        $student = DB::transaction(function () use ($application) {
            $student = Student::create([
                'admission_no' => 'ADM-' . now()->format('Y') . '-' . str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'middle_name' => $application->middle_name,
                'gender' => $application->gender,
                'date_of_birth' => $application->date_of_birth,
                'section_id' => $application->section_id,
                'class_id' => $application->class_id,
                'date_admitted' => now()->toDateString(),
                'status' => 'active',
                'address' => $application->address,
            ]);

            $application->update([
                'status' => 'converted',
                'student_id' => $student->id,
                'reviewed_by' => Auth::guard('school')->id(),
                'reviewed_at' => now(),
            ]);

            return $student;
        });

        return redirect('/school/' . $request->route('school') . '/students/' . $student->id)
            ->with('success', 'Applicant enrolled as a student. Add the parent/guardian link from the student profile.');
    }
}
