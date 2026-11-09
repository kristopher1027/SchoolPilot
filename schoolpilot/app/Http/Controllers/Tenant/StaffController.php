<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function index()
    {
        $staff = Staff::with('user')->latest()->paginate(20);

        return view('tenant.staff.index', compact('staff'));
    }

    public function create()
    {
        $roles = [
            'principal',
            'vice_principal',
            'dean',
            'hod',
            'class_teacher',
            'subject_teacher',
            'bursar',
        ];

        return view('tenant.staff.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:principal,vice_principal,dean,hod,class_teacher,subject_teacher,bursar'],
            'staff_no' => ['required', 'string', 'max:255', 'unique:staff,staff_no'],
            'designation' => ['nullable', 'string', 'max:255'],
            'date_hired' => ['required', 'date'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'is_active' => true,
            ]);

            Staff::create([
                'user_id' => $user->id,
                'staff_no' => $data['staff_no'],
                'designation' => $data['designation'] ?? null,
                'date_hired' => $data['date_hired'],
            ]);
        });

        return redirect('/school/' . $request->route('school') . '/staff')->with(
            'success',
            'Staff member added successfully. Share the initial password securely and ask the staff member to change it.'
        );
    }
}
