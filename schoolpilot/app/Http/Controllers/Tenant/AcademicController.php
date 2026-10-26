<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Section;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Subject;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AcademicController extends Controller
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
        $sessions = AcademicSession::with('terms')->get();
        $sections = Section::all();
        $classes = SchoolClass::with('section')->get();
        $subjects = Subject::with('section')->get();

        return view('tenant.academic.index', compact('sessions', 'sections', 'classes', 'subjects'));
    }

    public function storeSession(Request $request)
    {
        $request->validate(['name' => 'required|string', 'start_date' => 'required|date', 'end_date' => 'required|date']);
        AcademicSession::create($request->all());
        return back()->with('success', 'Session created successfully!');
    }

    public function storeTerm(Request $request)
    {
        $request->validate(['academic_session_id' => 'required', 'name' => 'required|string', 'start_date' => 'required|date', 'end_date' => 'required|date']);
        Term::create($request->all());
        return back()->with('success', 'Term created successfully!');
    }

    public function storeClass(Request $request)
    {
        $request->validate(['section_id' => 'required', 'name' => 'required|string', 'level' => 'required|integer']);
        SchoolClass::create($request->all());
        return back()->with('success', 'Class created successfully!');
    }

    public function storeSubject(Request $request)
    {
        $request->validate(['name' => 'required|string', 'section_id' => 'nullable']);
        Subject::create($request->all());
        return back()->with('success', 'Subject created successfully!');
    }
}