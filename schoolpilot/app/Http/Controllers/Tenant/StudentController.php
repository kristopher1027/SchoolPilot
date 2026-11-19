<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::query()->with(['section', 'schoolClass']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('admission_no', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('section_id')) {
            $query->where('section_id', $request->integer('section_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->integer('class_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $students = $query->latest()->paginate(20)->withQueryString();
        $sections = Section::query()->orderBy('name')->get();
        $classes = SchoolClass::query()->orderBy('name')->get();

        return view('tenant.students.index', compact('students', 'sections', 'classes'));
    }

    public function create()
    {
        $sections = Section::query()->orderBy('name')->get();
        $classes = SchoolClass::query()->orderBy('name')->get();

        return view('tenant.students.create', compact('sections', 'classes'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedStudentData($request);
        if (! $this->classBelongsToSection($data)) {
            return back()->withErrors(['class_id' => 'The selected class does not belong to the selected section.'])->withInput();
        }

        $data['status'] = 'active';

        $student = DB::transaction(function () use ($data) {
            $student = Student::create($data);
            $student->enrollmentHistory()->create([
                'to_section_id' => $student->section_id,
                'to_class_id' => $student->class_id,
                'to_status' => $student->status,
                'change_type' => 'enrollment',
                'reason' => 'Initial enrollment',
                'effective_on' => $student->date_admitted,
                'changed_by' => auth('school')->id(),
            ]);

            return $student;
        });

        return redirect('/school/' . $request->route('school') . '/students/' . $student->id)
            ->with('success', 'Student enrolled successfully.');
    }

    public function show(Request $request, Student $student)
    {
        $student->load([
            'section',
            'schoolClass',
            'guardians.user',
            'enrollmentHistory.fromSection',
            'enrollmentHistory.toSection',
            'enrollmentHistory.fromClass',
            'enrollmentHistory.toClass',
            'enrollmentHistory.changedBy',
        ]);
        $availableGuardians = Guardian::query()->with('user')->orderBy('id')->get();
        $sections = Section::query()->orderBy('name')->get();
        $classes = SchoolClass::query()->orderBy('name')->get();

        return view('tenant.students.show', compact('student', 'availableGuardians', 'sections', 'classes'));
    }

    public function edit(Student $student)
    {
        return view('tenant.students.edit', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $this->validatedStudentData($request, $student);
        $student->update($data);

        return redirect('/school/' . $request->route('school') . '/students/' . $student->id)
            ->with('success', 'Student personal record updated successfully.');
    }

    public function changeLifecycle(Request $request, int $student)
    {
        $student = Student::query()->findOrFail($student);

        $data = $request->validate([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'status' => ['required', Rule::in(['active', 'graduated', 'transferred', 'withdrawn'])],
            'effective_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        if (! $this->classBelongsToSection($data)) {
            throw ValidationException::withMessages([
                'class_id' => 'The selected class does not belong to the selected section.',
            ]);
        }

        $placementChanged = (int) $student->section_id !== (int) $data['section_id']
            || (int) ($student->class_id ?? 0) !== (int) ($data['class_id'] ?? 0);
        $statusChanged = $student->status !== $data['status'];

        if (! $placementChanged && ! $statusChanged) {
            throw ValidationException::withMessages([
                'reason' => 'Choose a new class placement or a different student status to record a change.',
            ]);
        }

        DB::transaction(function () use ($student, $data, $placementChanged, $statusChanged) {
            $fromSectionId = $student->section_id;
            $fromClassId = $student->class_id;
            $fromStatus = $student->status;

            $changeType = match (true) {
                $statusChanged && $data['status'] !== 'active' => $data['status'],
                $placementChanged && $fromSectionId !== (int) $data['section_id'] => 'section_transfer',
                $placementChanged && $this->isPromotion($fromClassId, isset($data['class_id']) ? (int) $data['class_id'] : null) => 'promotion',
                $placementChanged => 'class_placement',
                default => 'status_change',
            };

            $student->update([
                'section_id' => $data['section_id'],
                'class_id' => $data['class_id'] ?? null,
                'status' => $data['status'],
            ]);

            $student->enrollmentHistory()->create([
                'from_section_id' => $fromSectionId,
                'to_section_id' => $student->section_id,
                'from_class_id' => $fromClassId,
                'to_class_id' => $student->class_id,
                'from_status' => $fromStatus,
                'to_status' => $student->status,
                'change_type' => $changeType,
                'reason' => $data['reason'],
                'effective_on' => $data['effective_on'],
                'changed_by' => auth('school')->id(),
            ]);
        });

        return redirect('/school/' . $request->route('school') . '/students/' . $student->id)
            ->with('success', 'Student placement/status updated and history recorded.');
    }

    private function isPromotion(?int $fromClassId, ?int $toClassId): bool
    {
        if (! $fromClassId || ! $toClassId || $fromClassId === (int) $toClassId) {
            return false;
        }

        $fromLevel = SchoolClass::query()->whereKey($fromClassId)->value('level');
        $toLevel = SchoolClass::query()->whereKey($toClassId)->value('level');

        return $fromLevel !== null && $toLevel !== null && $toLevel > $fromLevel;
    }

    private function classBelongsToSection(array $data): bool
    {
        if (empty($data['class_id'])) {
            return true;
        }

        return SchoolClass::query()->whereKey($data['class_id'])
            ->where('section_id', $data['section_id'])->exists();
    }

    private function validatedStudentData(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'admission_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('students', 'admission_no')->ignore($student?->id),
            ],
            'date_admitted' => ['required', 'date'],
            'address' => ['nullable', 'string', 'max:5000'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'genotype' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
