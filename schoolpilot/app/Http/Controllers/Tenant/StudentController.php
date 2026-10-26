<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
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
        $students = Student::latest()->paginate(20);
        return view('tenant.students.index', compact('students'));
    }

    public function create()
    {
        $sections = Section::all();
        $classes = SchoolClass::all();
        return view('tenant.students.create', compact('sections', 'classes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date',
            'section_id' => 'required|exists:sections,id',
            'class_id' => 'nullable|exists:classes,id',
            'admission_no' => 'required|unique:students,admission_no',
            'date_admitted' => 'required|date',
        ]);

        $data['status'] = 'active';

        Student::create($data);

        return redirect('/school/greenfield/students')->with('success', 'Student added successfully!');
    }
}