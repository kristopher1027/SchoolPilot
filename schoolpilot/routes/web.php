<?php

use App\Http\Controllers\Tenant\StudentController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\AcademicController;
use App\Http\Controllers\Tenant\ScoreController;
use App\Http\Controllers\Tenant\ResultController;
use App\Http\Controllers\Tenant\TeacherController;
use App\Http\Controllers\Tenant\AssignmentController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/login', function () {
    return view('tenant.login');
})->middleware('guest:school');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $email = strtolower(trim($credentials['email']));
    $matches = [];

    // Read only registered, active tenant metadata from the central database.
    $registeredTenants = DB::connection('central_sqlite')->table('tenants')->get(['data']);
    foreach ($registeredTenants as $registeredTenant) {
        $metadata = json_decode($registeredTenant->data ?? '{}', true);
        if (! is_array($metadata)) {
            continue;
        }

        $slug = $metadata['slug'] ?? null;
        $databaseName = $metadata['db_name'] ?? null;
        if (
            ! is_string($slug) ||
            ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) ||
            ($metadata['status'] ?? 'active') !== 'active' ||
            ! is_string($databaseName) ||
            ! preg_match('/^[a-zA-Z0-9_-]+\.sqlite$/', $databaseName)
        ) {
            continue;
        }

        $databasePath = database_path('tenants/' . $databaseName);
        if (! is_file($databasePath)) {
            continue;
        }

        Config::set('database.connections.tenant_sqlite.database', $databasePath);
        DB::purge('tenant_sqlite');

        $user = DB::connection('tenant_sqlite')->table('users')
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            $matches[] = ['slug' => $slug, 'user_id' => $user->id, 'role' => $user->role];
        }
    }

    // Keep support for the original Greenfield demo database before it is
    // added to the central tenant registry.
    if (! collect($registeredTenants)->contains(function ($tenant) {
        $metadata = json_decode($tenant->data ?? '{}', true);
        return is_array($metadata) && ($metadata['slug'] ?? null) === 'greenfield';
    })) {
        $legacyDatabase = database_path('tenants/greenfield.sqlite');
        if (is_file($legacyDatabase)) {
            Config::set('database.connections.tenant_sqlite.database', $legacyDatabase);
            DB::purge('tenant_sqlite');

            $user = DB::connection('tenant_sqlite')->table('users')
                ->where('email', $email)
                ->where('is_active', true)
                ->first();

            if ($user && Hash::check($credentials['password'], $user->password)) {
                $matches[] = ['slug' => 'greenfield', 'user_id' => $user->id, 'role' => $user->role];
            }
        }
    }

    if (count($matches) !== 1) {
        DB::purge('tenant_sqlite');
        Config::set('database.connections.tenant_sqlite.database', null);

        return back()
            ->withErrors(['email' => 'The email or password is incorrect, or the account is unavailable.'])
            ->onlyInput('email');
    }

    $match = $matches[0];
    $databasePath = database_path('tenants/' . $match['slug'] . '.sqlite');

    // Resolve the registered database filename rather than trusting the URL slug.
    foreach ($registeredTenants as $registeredTenant) {
        $metadata = json_decode($registeredTenant->data ?? '{}', true);
        if (is_array($metadata) && ($metadata['slug'] ?? null) === $match['slug']) {
            $databasePath = database_path('tenants/' . $metadata['db_name']);
            break;
        }
    }

    Config::set('database.connections.tenant_sqlite.database', $databasePath);
    DB::purge('tenant_sqlite');

    $schoolUser = \App\Models\Tenant\User::query()->findOrFail($match['user_id']);
    Auth::guard('school')->login($schoolUser, $request->boolean('remember'));
    $request->session()->regenerate();
    $request->session()->put('_school_authenticated_slug', $match['slug']);

    DB::purge('tenant_sqlite');
    Config::set('database.connections.tenant_sqlite.database', null);

    return redirect('/school/' . $match['slug']);
})->middleware('throttle:5,1');

Route::prefix('school/{school}')->group(function () {
        Route::middleware(['auth:school', EnsureActiveUser::class])->get('/', function () {
            $user = Auth::guard('school')->user();

            if (in_array($user->role, ['class_teacher', 'subject_teacher'], true)) {
                return redirect('/school/' . request()->route('school') . '/teacher-dashboard');
            }

            return redirect('/school/' . request()->route('school') . '/dashboard');
        });

        Route::middleware(['auth:school', EnsureActiveUser::class])->group(function () {
            Route::post('/logout', function (Request $request) {
                Auth::guard('school')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/school/' . $request->route('school') . '/login');
            });

            Route::get('/dashboard', function () {
                $totalStudents = Student::count();
                $totalStaff = User::whereIn('role', [
                    'principal', 'vice_principal', 'dean', 'hod',
                    'class_teacher', 'subject_teacher', 'bursar',
                ])->count();
                $totalClasses = SchoolClass::count();
                $currentSession = AcademicSession::where('is_current', true)->first();

                return view('tenant.dashboard', compact(
                    'totalStudents',
                    'totalStaff',
                    'totalClasses',
                    'currentSession'
                ));
            })->middleware(RoleMiddleware::class . ':school_admin,principal,vice_principal,dean,hod,class_teacher,subject_teacher,bursar');

            Route::get('/teacher-dashboard', [TeacherController::class, 'dashboard'])
                ->middleware(RoleMiddleware::class . ':class_teacher,subject_teacher');

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,dean')->group(function () {
                Route::get('/students', [StudentController::class, 'index']);
                Route::get('/students/create', [StudentController::class, 'create']);
                Route::post('/students', [StudentController::class, 'store']);
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal')->group(function () {
                Route::get('/staff', [StaffController::class, 'index']);
                Route::get('/staff/create', [StaffController::class, 'create']);
                Route::post('/staff', [StaffController::class, 'store']);

                Route::get('/academic', [AcademicController::class, 'index']);
                Route::post('/academic/sessions', [AcademicController::class, 'storeSession']);
                Route::post('/academic/terms', [AcademicController::class, 'storeTerm']);
                Route::post('/academic/classes', [AcademicController::class, 'storeClass']);
                Route::post('/academic/subjects', [AcademicController::class, 'storeSubject']);

                Route::get('/assignments', [AssignmentController::class, 'index']);
                Route::post('/assignments', [AssignmentController::class, 'store']);
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,dean,hod,class_teacher,subject_teacher')->group(function () {
                Route::get('/scores', [ScoreController::class, 'index']);
                Route::get('/scores/entry', [ScoreController::class, 'entry']);
                Route::post('/scores/store', [ScoreController::class, 'store']);
                Route::post('/scores/submit', [ScoreController::class, 'submitForApproval']);
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,dean,hod,class_teacher')->group(function () {
                Route::get('/results', [ResultController::class, 'index']);
                Route::get('/results/class', [ResultController::class, 'classResults']);
                Route::get('/results/report-card/{studentId}', [ResultController::class, 'reportCard']);
            });

            Route::post('/results/approve', [ResultController::class, 'approve'])
                ->middleware(RoleMiddleware::class . ':school_admin,principal,vice_principal,dean,hod,class_teacher');
        });
    });
