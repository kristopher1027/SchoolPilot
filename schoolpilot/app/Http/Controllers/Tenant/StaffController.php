<?php

namespace App\\Http\\Controllers\\Tenant;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Tenant\\Department;
use App\\Models\\Tenant\\Staff;
use App\\Models\\Tenant\\User;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Validation\\Rule;

class StaffController extends Controller
{
    private const ROLES = [
        'principal', 'vice_principal', 'dean', 'hod',
        'class_teacher', 'subject_teacher', 'bursar',
    ];

    public function index(Request $request)
    {
        $query = Staff::query()->with(['user', 'departments']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($staffQuery) use ($search) {
                $staffQuery->where('staff_no', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('role') && in_array($request->query('role'), self::ROLES, true)) {
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('role', $request->query('role')));
        }

        if ($request->query('status') === 'active' || $request->query('status') === 'inactive') {
            $active = $request->query('status') === 'active';
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('is_active', $active));
        }

        if ($request->filled('department_id') && ctype_digit((string) $request->query('department_id'))) {
            $query->whereHas('departments', fn ($departmentQuery) => $departmentQuery->whereKey((int) $request->query('department_id')));
        }

        $staff = $query->latest()->paginate(20)->withQueryString();
        $departments = Department::query()->orderBy('name')->get();
        $roles = self::ROLES;

        return view('tenant.staff.index', compact('staff', 'departments', 'roles'));
    }

    public function create()
    {
        $roles = self::ROLES;
        $departments = Department::query()->orderBy('name')->get();

        return view('tenant.staff.create', compact('roles', 'departments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(self::ROLES)],
            'staff_no' => ['required', 'string', 'max:255', 'unique:staff,staff_no'],
            'designation' => ['nullable', 'string', 'max:255'],
            'date_hired' => ['nullable', 'date', 'before_or_equal:today'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'distinct', 'exists:departments,id'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $staff = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'is_active' => true,
            ]);

            $staff = Staff::create([
                'user_id' => $user->id,
                'staff_no' => $data['staff_no'],
                'designation' => $data['designation'] ?? null,
                'date_hired' => $data['date_hired'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $this->syncDepartments($staff, $data['department_ids'] ?? [], $data['role']);

            return $staff;
        });

        return redirect($this->schoolPath($request, '/staff/' . $staff->id))
            ->with('success', 'Staff member added successfully.');
    }

    public function show(Staff $staff)
    {
        $staff->load(['user', 'departments']);

        return view('tenant.staff.show', compact('staff'));
    }

    public function edit(Staff $staff)
    {
        $staff->load(['user', 'departments']);
        $roles = self::ROLES;
        $departments = Department::query()->orderBy('name')->get();
        $selectedDepartmentIds = $staff->departments->modelKeys();

        return view('tenant.staff.edit', compact('staff', 'roles', 'departments', 'selectedDepartmentIds'));
    }

    public function update(Request $request, Staff $staff)
    {
        $staff->load('user');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->user_id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(self::ROLES)],
            'staff_no' => ['required', 'string', 'max:255', Rule::unique('staff', 'staff_no')->ignore($staff->id)],
            'designation' => ['nullable', 'string', 'max:255'],
            'date_hired' => ['nullable', 'date', 'before_or_equal:today'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'distinct', 'exists:departments,id'],
        ]);

        DB::transaction(function () use ($data, $staff) {
            $staff->user->update([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
            ]);

            $staff->update([
                'staff_no' => $data['staff_no'],
                'designation' => $data['designation'] ?? null,
                'date_hired' => $data['date_hired'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $this->syncDepartments($staff, $data['department_ids'] ?? [], $data['role']);
        });

        return redirect($this->schoolPath($request, '/staff/' . $staff->id))
            ->with('success', 'Staff details updated successfully.');
    }

    public function toggleStatus(Request $request, Staff $staff)
    {
        $staff->load('user');

        if ((int) $staff->user_id === (int) $request->user('school')?->id) {
            return back()->withErrors(['status' => 'You cannot deactivate your own account.']);
        }

        $staff->user->update(['is_active' => ! $staff->user->is_active]);

        $message = $staff->user->is_active
            ? 'Staff account activated.'
            : 'Staff account deactivated.';

        return back()->with('success', $message);
    }

    private function syncDepartments(Staff $staff, array $departmentIds, string $role): void
    {
        $pivot = [];
        foreach ($departmentIds as $departmentId) {
            $pivot[$departmentId] = ['is_hod' => $role === 'hod'];
        }

        $staff->departments()->sync($pivot);
    }

    private function schoolPath(Request $request, string $path): string
    {
        return '/school/' . $request->route('school') . $path;
    }
}
