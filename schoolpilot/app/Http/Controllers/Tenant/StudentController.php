<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Section;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $student = Student::create($data);

        return redirect('/school/' . $request->route('school') . '/students/' . $student->id)
            ->with('success', 'Student enrolled successfully.');
    }

    public function show(Request $request, Student $student)
    {
        $student->load(['section', 'schoolClass', 'guardians.user']);
        $availableGuardians = \\App\\Models\\Tenant\\Guardian::query()->with('user')->orderBy('id')->get();

        return view('tenant.students.show', compact('student', 'availableGuardians'));
    }

    public function edit(Student $student)
    {
        $sections = Section::query()->orderBy('name')->get();
        $classes = SchoolClass::query()->orderBy('name')->get();

        return view('tenant.students.edit', compact('student', 'sections', 'classes'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $this->validatedStudentData($request, $student);
        if (! $this->classBelongsToSection($data)) {
            return back()->withErrors(['class_id' => 'The selected class does not belong to the selected section.'])->withInput();
        }
        $student->update($data);

        return redirect('/school/' . $request->route('school') . '/students/' . $student->id)
            ->with('success', 'Student record updated successfully.');
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
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'admission_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('students', 'admission_no')->ignore($student?->id),
            ],
            'date_admitted' => ['required', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'graduated', 'transferred', 'withdrawn'])],
            'address' => ['nullable', 'string', 'max:5000'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'genotype' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string', 'max:5000'],
        ]);



        return $data;
    }
}
