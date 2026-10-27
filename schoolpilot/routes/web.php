<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\StudentController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\AcademicController;
use App\Http\Controllers\Tenant\ScoreController;
use App\Http\Controllers\Tenant\ResultController;
use App\Http\Controllers\Tenant\TeacherController;
use App\Http\Controllers\Tenant\AssignmentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('school/greenfield')->group(function () {

    // --- 1. LOGIN ROUTES ---
    Route::get('/login', function () {
        return view('tenant.login');
    });


    Route::post('/login', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);


        $dbPath = database_path('tenants/greenfield.sqlite');

        Config::set('database.connections.tenant_sqlite.database', $dbPath);
        DB::purge('tenant_sqlite');

        $user = \App\Models\Tenant\User::on('tenant_sqlite')->where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            Auth::login($user);
            $request->session()->regenerate();

            Config::set('database.default', 'tenant_sqlite');


            // If Teacher, go to Teacher Portal
            if (in_array($user->role, ['class_teacher', 'subject_teacher'])) {
                return redirect('/school/greenfield/teacher-dashboard');
            }

            // If Admin/Principal, show main dashboard
            $totalStudents = \App\Models\Tenant\Student::count();
            $totalStaff = \App\Models\Tenant\User::whereIn('role', ['principal', 'vice_principal', 'dean', 'hod', 'class_teacher', 'subject_teacher', 'bursar'])->count();
            $totalClasses = \App\Models\Tenant\SchoolClass::count();
            $currentSession = \App\Models\Tenant\AcademicSession::where('is_current', true)->first();

            return view('tenant.dashboard', compact('totalStudents', 'totalStaff', 'totalClasses', 'currentSession'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    });

    // --- 2. LOGOUT ---
    Route::post('/logout', function () {
        Auth::logout();
        return redirect('/school/greenfield/login');
    });

    // --- 3. DASHBOARD ---
    Route::get('/dashboard', function () {
        if (!Auth::check()) return redirect('/school/greenfield/login');
        
        Config::set('database.connections.tenant_sqlite.database', database_path('tenants/greenfield.sqlite'));
        DB::purge('tenant_sqlite');
        Config::set('database.default', 'tenant_sqlite');

        $totalStudents = \App\Models\Tenant\Student::count();
        $totalStaff = \App\Models\Tenant\User::whereIn('role', ['principal', 'vice_principal', 'dean', 'hod', 'class_teacher', 'subject_teacher', 'bursar'])->count();
        $totalClasses = \App\Models\Tenant\SchoolClass::count();
        $currentSession = \App\Models\Tenant\AcademicSession::where('is_current', true)->first();

        return view('tenant.dashboard', compact('totalStudents', 'totalStaff', 'totalClasses', 'currentSession'));
    });

    // --- 4. TEACHER PORTAL ---
    Route::get('/teacher-dashboard', [TeacherController::class, 'dashboard']);

    // --- 5. STUDENTS ---
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/create', [StudentController::class, 'create']);
    Route::post('/students', [StudentController::class, 'store']);

    // --- 6. STAFF ---
    Route::get('/staff', [StaffController::class, 'index']);
    Route::get('/staff/create', [StaffController::class, 'create']);
    Route::post('/staff', [StaffController::class, 'store']);

    // ---7 Assignments Routes
    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::post('/assignments', [AssignmentController::class, 'store']);

    // --- 7. ACADEMIC ---
    Route::get('/academic', [AcademicController::class, 'index']);
    Route::post('/academic/sessions', [AcademicController::class, 'storeSession']);
    Route::post('/academic/terms', [AcademicController::class, 'storeTerm']);
    Route::post('/academic/classes', [AcademicController::class, 'storeClass']);
    Route::post('/academic/subjects', [AcademicController::class, 'storeSubject']);

    // --- 8. SCORES ---
    Route::get('/scores', [ScoreController::class, 'index']);
    Route::get('/scores/entry', [ScoreController::class, 'entry']);
    Route::post('/scores/store', [ScoreController::class, 'store']);

    // --- 9. RESULTS ---
    Route::get('/results', [ResultController::class, 'index']);
    Route::get('/results/class', [ResultController::class, 'classResults']);
    Route::get('/results/report-card/{studentId}', [ResultController::class, 'reportCard']);

});