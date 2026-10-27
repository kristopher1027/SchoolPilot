<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use App\Models\Tenant\User;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Subject;
use App\Models\Tenant\TeacherSubject;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function __construct()
    {
        Config::set('database.connections.tenant_sqlite.database', database_path('tenants/greenfield.sqlite'));
        DB::purge('tenant_sqlite');
        Config::set('database.default', 'tenant_sqlite');
    }

    public function index()
    {
        $teachers = Staff::whereHas('user', function($q) {
            $q->whereIn('role', ['class_teacher', 'subject_teacher', 'hod', 'dean']);
        })->with('user')->get();

        $classes = SchoolClass::all();
        $subjects = Subject::all();
        $sessions = AcademicSession::all();
        $terms = Term::all();

        // Load existing assignments
        $assignments = TeacherSubject::with(['subject', 'schoolClass'])->get();

        return view('tenant.assignments.index', compact('teachers', 'classes', 'subjects', 'sessions', 'terms', 'assignments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required',
            'class_id' => 'required',
            'subject_id' => 'required',
            'session_id' => 'required',
            'term_id' => 'required',
        ]);

        TeacherSubject::firstOrCreate([
            'staff_id' => $request->staff_id,
            'class_id' => $request->class_id,
            'subject_id' => $request->subject_id,
            'academic_session_id' => $request->session_id,
            'term_id' => $request->term_id,
        ]);

        return back()->with('success', 'Teacher assigned successfully!');
    }
}