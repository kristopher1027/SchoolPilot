<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use App\Models\Tenant\TeacherSubject;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TeacherController extends Controller
{
    public function __construct()
    {
        Config::set('database.connections.tenant_sqlite.database', database_path('tenants/greenfield.sqlite'));
        DB::purge('tenant_sqlite');
        Config::set('database.default', 'tenant_sqlite');
    }

    public function dashboard()
    {
        $staff = Staff::where('user_id', Auth::id())->first();

        if (!$staff) {
            return view('tenant.teacher.dashboard', ['assignments' => collect()]);
        }

        $assignments = TeacherSubject::where('staff_id', $staff->id)
            ->with(['schoolClass', 'subject'])
            ->get();

        return view('tenant.teacher.dashboard', compact('staff', 'assignments'));
    }
}