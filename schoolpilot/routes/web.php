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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('school/greenfield')->group(function () {
        Route::get('/login', function () {
            return view('tenant.login');
        });

        Route::post('/login', function (Request $request) {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            if (! Auth::guard('school')->attempt(
                [...$credentials, 'is_active' => true],
                $request->boolean('remember')
            )) {
                return back()
                    ->withErrors(['email' => 'Invalid credentials or inactive account.'])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();

            $user = Auth::guard('school')->user();

            if (in_array($user->role, ['class_teacher', 'subject_teacher'], true)) {
                return redirect('/school/greenfield/teacher-dashboard');
            }

            return redirect('/school/greenfield/dashboard');
        })->middleware('throttle:5,1');

        Route::middleware(['auth:school', EnsureActiveUser::class])->group(function () {
            Route::post('/logout', function (Request $request) {
                Auth::guard('school')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/school/greenfield/login');
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
            });

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
                ->middleware(RoleMiddleware::class . ':school_admin,principal,vice_principal');
        });
    });
