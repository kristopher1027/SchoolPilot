<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuardianController extends Controller
{
    public function index(Request $request)
    {
        $query = Guardian::query()->with('user')->withCount('students');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $guardians = $query->latest()->paginate(20)->withQueryString();

        return view('tenant.parents.index', compact('guardians'));
    }

    public function create()
    {
        return view('tenant.parents.create');
    }

    public function store(Request $request)
    {
        $data = $this->validatedGuardianData($request);

        $guardian = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Str::random(64),
                'role' => 'parent',
                // Parent portal access is enabled in a later milestone.
                'is_active' => false,
            ]);

            return Guardian::create([
                'user_id' => $user->id,
                'occupation' => $data['occupation'] ?? null,
                'address' => $data['address'] ?? null,
                'relationship' => $data['relationship'] ?? null,
            ]);
        });

        return redirect('/school/' . $request->route('school') . '/parents/' . $guardian->id . '/edit')
            ->with('success', 'Parent/guardian record created. Link the guardian to the relevant student profile.');
    }

    public function show(Guardian $guardian)
    {
        $guardian->load([
            'user',
            'students' => fn ($query) => $query
                ->with(['section', 'schoolClass'])
                ->orderBy('last_name')
                ->orderBy('first_name'),
        ]);

        return view('tenant.parents.show', compact('guardian'));
    }

    public function edit(Guardian $guardian)
    {
        $guardian->load('user');

        return view('tenant.parents.edit', compact('guardian'));
    }

    public function update(Request $request, Guardian $guardian)
    {
        $data = $this->validatedGuardianData($request, $guardian);

        DB::transaction(function () use ($data, $guardian) {
            $guardian->user->update([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
            ]);

            $guardian->update([
                'occupation' => $data['occupation'] ?? null,
                'address' => $data['address'] ?? null,
                'relationship' => $data['relationship'] ?? null,
            ]);

            if (! empty($data['portal_password'])) {
                $guardian->user->update([
                    'password' => Hash::make($data['portal_password']),
                    'is_active' => true,
                ]);
            }
        });

        return redirect('/school/' . $request->route('school') . '/parents')
            ->with('success', 'Parent/guardian record updated.');
    }

    public function attachToStudent(Request $request, Student $student)
    {
        $data = $request->validate([
            'guardian_id' => ['required', 'integer', 'exists:parents,id'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $guardianId = (int) $data['guardian_id'];
        $isPrimary = (bool) ($data['is_primary'] ?? false);

        DB::transaction(function () use ($student, $guardianId, $isPrimary) {
            if ($isPrimary) {
                DB::table('student_parents')->where('student_id', $student->id)->update(['is_primary' => false]);
            }

            $student->guardians()->syncWithoutDetaching([$guardianId]);
            $student->guardians()->updateExistingPivot($guardianId, ['is_primary' => $isPrimary]);
        });

        return back()->with('success', 'Guardian linked to student.');
    }

    public function detachFromStudent(Student $student, Guardian $guardian)
    {
        $student->guardians()->detach($guardian->id);

        return back()->with('success', 'Guardian unlinked from student.');
    }

    private function validatedGuardianData(Request $request, ?Guardian $guardian = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($guardian?->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'portal_password' => ['sometimes', 'nullable', 'string', 'min:12', 'max:255'],
        ]);
    }
}
