<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function __construct()
    {
        // Force the controller to use the greenfield database
        Config::set('database.connections.tenant_sqlite.database', database_path('tenants/greenfield.sqlite'));
        DB::purge('tenant_sqlite');
        Config::set('database.default', 'tenant_sqlite');
    }

    public function index()
    {
        $staff = Staff::with('user')->latest()->paginate(20);
        return view('tenant.staff.index', compact('staff'));
    }

    public function create()
    {
        // We need a list of roles for the dropdown
        $roles = [
            'principal', 'vice_principal', 'dean', 'hod', 
            'class_teacher', 'subject_teacher', 'bursar'
        ];
        return view('tenant.staff.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:principal,vice_principal,dean,hod,class_teacher,subject_teacher,bursar',
            'staff_no' => 'required|unique:staff,staff_no',
            'designation' => 'nullable|string|max:255',
            'date_hired' => 'required|date',
        ]);

        // 1. Create the User account first
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make('password'), // Default password for all new staff
            'role' => $data['role'],
            'is_active' => true,
        ]);

        // 2. Create the Staff profile linked to the user
        Staff::create([
            'user_id' => $user->id,
            'staff_no' => $data['staff_no'],
            'designation' => $data['designation'] ?? null,
            'date_hired' => $data['date_hired'],
        ]);

        return redirect('/school/greenfield/staff')->with('success', 'Staff member added successfully! Default password is "password".');
    }
}