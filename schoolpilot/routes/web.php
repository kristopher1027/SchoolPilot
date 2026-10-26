<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\StudentController;
use App\Http\Controllers\Tenant\StaffController;use App\Http\Controllers\Tenant\AcademicController;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('school/greenfield')->group(function () {

    // 1. Show Login Form
    Route::get('/login', function () {
        return view('tenant.login');
    });

        // 2. Handle Login (AND show dashboard immediately to avoid redirect issues)
    Route::post('/login', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Connect to tenant DB
        $dbPath = database_path('tenants/greenfield.sqlite');
        if (!file_exists($dbPath)) {
            return back()->withErrors(['email' => "Database missing at $dbPath"]);
        }
        Config::set('database.connections.tenant_sqlite.database', $dbPath);
        DB::purge('tenant_sqlite');

        $user = \App\Models\Tenant\User::on('tenant_sqlite')->where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            Auth::login($user);
            $request->session()->regenerate();
            
            // Setup DB for dashboard
            Config::set('database.default', 'tenant_sqlite');
            
            // Fetch all stats for the dashboard
            $totalStudents = \App\Models\Tenant\Student::count();
            $totalStaff = \App\Models\Tenant\User::whereIn('role', ['principal', 'vice_principal', 'dean', 'hod', 'class_teacher', 'subject_teacher', 'bursar'])->count();
            $totalClasses = \App\Models\Tenant\SchoolClass::count();
            $currentSession = \App\Models\Tenant\AcademicSession::where('is_current', true)->first();

            // Render dashboard immediately!
            return view('tenant.dashboard', compact('totalStudents', 'totalStaff', 'totalClasses', 'currentSession'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    });

    // 3. Logout
    Route::post('/logout', function () {
        Auth::logout();
        return redirect('/school/greenfield/login');
    });

    // 4. Dashboard (if you click the sidebar link later)
        Route::get('/dashboard', function () {
        if (!Auth::check()) return redirect('/school/greenfield/login');
        
        Config::set('database.connections.tenant_sqlite.database', database_path('tenants/greenfield.sqlite'));
        DB::purge('tenant_sqlite');
        Config::set('database.default', 'tenant_sqlite');

        // Fetch counts for the dashboard
        $totalStudents = \App\Models\Tenant\Student::count();
        $totalStaff = \App\Models\Tenant\User::whereIn('role', ['principal', 'vice_principal', 'dean', 'hod', 'class_teacher', 'subject_teacher', 'bursar'])->count();
        $totalClasses = \App\Models\Tenant\SchoolClass::count();
        $currentSession = \App\Models\Tenant\AcademicSession::where('is_current', true)->first();

        return view('tenant.dashboard', compact('totalStudents', 'totalStaff', 'totalClasses', 'currentSession'));
    });
    // Academic Routes
    Route::get('/academic', [AcademicController::class, 'index']);
    Route::post('/academic/sessions', [AcademicController::class, 'storeSession']);
    Route::post('/academic/terms', [AcademicController::class, 'storeTerm']);
    Route::post('/academic/classes', [AcademicController::class, 'storeClass']);
    Route::post('/academic/subjects', [AcademicController::class, 'storeSubject']);
    // 5. Students Routes
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/create', [StudentController::class, 'create']);
    Route::post('/students', [StudentController::class, 'store']);
        // Scores Routes
    Route::get('/scores', [\App\Http\Controllers\Tenant\ScoreController::class, 'index']);
    Route::get('/scores/entry', [\App\Http\Controllers\Tenant\ScoreController::class, 'entry']);
    Route::post('/scores/store', [\App\Http\Controllers\Tenant\ScoreController::class, 'store']);
    // Results Routes
    Route::get('/results', [\App\Http\Controllers\Tenant\ResultController::class, 'index']);
    Route::get('/results/class', [\App\Http\Controllers\Tenant\ResultController::class, 'classResults']);
    Route::get('/results/report-card/{studentId}', [\App\Http\Controllers\Tenant\ResultController::class, 'reportCard']);
    // 6. Staff Routes (Added here!)
    Route::get('/staff', [StaffController::class, 'index']);
    Route::get('/staff/create', [StaffController::class, 'create']);
    Route::post('/staff', [StaffController::class, 'store']);
});