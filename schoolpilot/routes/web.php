<?php

use App\Http\Controllers\Tenant\StudentController;
use App\Http\Controllers\Tenant\GuardianController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\StaffHrController;
use App\Http\Controllers\Tenant\PayrollController;
use App\Http\Controllers\Tenant\AcademicController;
use App\Http\Controllers\Tenant\ScoreController;
use App\Http\Controllers\Tenant\ResultController;
use App\Http\Controllers\Tenant\TeacherController;
use App\Http\Controllers\Tenant\AssignmentController;
use App\Http\Controllers\Tenant\AttendanceController;
use App\Http\Controllers\Tenant\TimetableController;
use App\Http\Controllers\Tenant\AnnouncementController;
use App\Http\Controllers\Tenant\FeeController;
use App\Http\Controllers\Tenant\AdmissionController;
use App\Http\Controllers\Tenant\LibraryController;
use App\Http\Controllers\Tenant\InventoryController;
use App\Http\Controllers\Tenant\TransportController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SchoolWebsiteController;
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
});

Route::post('/login', function (Request $request) {
    // Preserve the application's default connection while inspecting tenant databases.
    $previousDefaultConnection = Config::get('database.default');

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
    Config::set('database.default', 'tenant_sqlite');
    DB::purge('tenant_sqlite');
    DB::setDefaultConnection('tenant_sqlite');

    $schoolUser = \App\Models\Tenant\User::query()->findOrFail($match['user_id']);
    Auth::guard('school')->login($schoolUser, $request->boolean('remember'));
    $request->session()->regenerate();
    $request->session()->put('_school_authenticated_slug', $match['slug']);

    DB::purge('tenant_sqlite');
    Config::set('database.default', $previousDefaultConnection);
    DB::setDefaultConnection($previousDefaultConnection);
    Config::set('database.connections.tenant_sqlite.database', null);

    return redirect('/school/' . $match['slug']);
})->middleware('throttle:5,1');

Route::prefix('school/{school}')->group(function () {
        Route::get('/admissions/apply', [AdmissionController::class, 'apply']);
        Route::post('/admissions/apply', [AdmissionController::class, 'submitApplication'])->middleware('throttle:5,1');

        // Public tenant website: all content is resolved from this school's database.
        Route::get('/website', [SchoolWebsiteController::class, 'publicHome']);
        Route::get('/website/pages/{slug}', [SchoolWebsiteController::class, 'publicPage']);
        Route::get('/website/news', [SchoolWebsiteController::class, 'publicNews']);
        Route::get('/website/gallery', [SchoolWebsiteController::class, 'publicGallery']);
        Route::get('/website/news/{slug}', [SchoolWebsiteController::class, 'publicPost']);
        Route::get('/website/events', [SchoolWebsiteController::class, 'publicEvents']);
        Route::get('/website/events/{slug}', [SchoolWebsiteController::class, 'publicEvent'])->where('slug', '[A-Za-z0-9-]+');
        Route::post('/website/events/{event}/register', [SchoolWebsiteController::class, 'registerForEvent'])->whereNumber('event')->middleware('throttle:5,1');

        // Keep old school-specific login bookmarks working after the universal login change.
        Route::get('/login', fn () => redirect('/login'));

        Route::middleware(['auth:school', EnsureActiveUser::class])->get('/', function () {
            $user = Auth::guard('school')->user();

            if (in_array($user->role, ['class_teacher', 'subject_teacher'], true)) {
                return redirect('/school/' . request()->route('school') . '/teacher-dashboard');
            }

            return redirect('/school/' . request()->route('school') . '/dashboard');
        });

        Route::middleware(['auth:school', EnsureActiveUser::class])->group(function () {
            Route::middleware(RoleMiddleware::class . ':school_admin,principal')->group(function () {
                Route::get('/website/manage', [SchoolWebsiteController::class, 'adminIndex']);
                Route::post('/website/settings', [SchoolWebsiteController::class, 'saveSettings']);
                Route::post('/website/pages', [SchoolWebsiteController::class, 'storePage']);
                Route::put('/website/pages/{page}', [SchoolWebsiteController::class, 'updatePage'])->whereNumber('page');
                Route::post('/website/pages/{page}/status', [SchoolWebsiteController::class, 'updatePageStatus'])->whereNumber('page');
                Route::post('/website/news', [SchoolWebsiteController::class, 'storePost']);
                Route::put('/website/news/{post}', [SchoolWebsiteController::class, 'updatePost'])->whereNumber('post');
                Route::post('/website/gallery', [SchoolWebsiteController::class, 'storeGalleryItem']);
                Route::post('/website/gallery/{item}/status', [SchoolWebsiteController::class, 'updateGalleryStatus'])->whereNumber('item');
                Route::post('/website/news/{post}/status', [SchoolWebsiteController::class, 'updatePostStatus'])->whereNumber('post');
                Route::post('/website/events', [SchoolWebsiteController::class, 'storeEvent']);
                Route::put('/website/events/{event}', [SchoolWebsiteController::class, 'updateEventDetails'])->whereNumber('event');
                Route::post('/website/events/{event}/status', [SchoolWebsiteController::class, 'updateEvent'])->whereNumber('event');
                Route::post('/website/clubs', [SchoolWebsiteController::class, 'storeClub']);
                Route::post('/website/club-members', [SchoolWebsiteController::class, 'storeClubMember']);
                Route::post('/website/clubs/{club}/members/{member}/end', [SchoolWebsiteController::class, 'endClubMember'])->whereNumber('club')->whereNumber('member');
                Route::post('/website/registrations/{registration}', [SchoolWebsiteController::class, 'updateRegistration'])->whereNumber('registration');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,vice_principal,dean,hod,bursar')->group(function () {
                Route::get('/reports', [ReportController::class, 'index']);
                Route::get('/reports/export', [ReportController::class, 'export']);
            });

            Route::get('/announcements', [AnnouncementController::class, 'index']);
            Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->whereNumber('announcement');
            Route::middleware(RoleMiddleware::class . ':school_admin,principal')->group(function () {
                Route::get('/announcements/create', [AnnouncementController::class, 'create']);
                Route::post('/announcements', [AnnouncementController::class, 'store']);
                Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->whereNumber('announcement');
                Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->whereNumber('announcement');
            });

            Route::post('/logout', function (Request $request) {
                Auth::guard('school')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login');
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
                Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student');
                Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->whereNumber('student');
                Route::put('/students/{student}', [StudentController::class, 'update'])->whereNumber('student');
                Route::post('/students/{student}/guardians', [GuardianController::class, 'attachToStudent'])->whereNumber('student');
                Route::delete('/students/{student}/guardians/{guardian}', [GuardianController::class, 'detachFromStudent'])->whereNumber('student')->whereNumber('guardian');
                Route::get('/parents', [GuardianController::class, 'index']);
                Route::get('/parents/{guardian}', [GuardianController::class, 'show'])->whereNumber('guardian');
                Route::get('/parents/create', [GuardianController::class, 'create']);
                Route::post('/parents', [GuardianController::class, 'store']);
                Route::get('/parents/{guardian}/edit', [GuardianController::class, 'edit'])->whereNumber('guardian');
                Route::put('/parents/{guardian}', [GuardianController::class, 'update'])->whereNumber('guardian');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,dean')->group(function () {
                Route::get('/admissions', [AdmissionController::class, 'index']);
                Route::get('/admissions/{application}', [AdmissionController::class, 'show'])->whereNumber('application');
                Route::put('/admissions/{application}/review', [AdmissionController::class, 'review'])->whereNumber('application');
                Route::post('/admissions/{application}/convert', [AdmissionController::class, 'convert'])->whereNumber('application');
            });


            Route::middleware(RoleMiddleware::class . ':school_admin,principal,vice_principal,dean,hod,class_teacher,subject_teacher,bursar')->group(function () {
                Route::get('/hr/leave', [StaffHrController::class, 'leaveIndex']);
                Route::post('/hr/leave', [StaffHrController::class, 'applyLeave'])->middleware('throttle:10,1');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal')->group(function () {
                Route::get('/hr/staff-attendance', [StaffHrController::class, 'attendanceIndex']);
                Route::post('/hr/staff-attendance', [StaffHrController::class, 'recordAttendance']);
                Route::post('/hr/leave-types', [StaffHrController::class, 'storeLeaveType']);
                Route::post('/hr/leave/{leave}/review', [StaffHrController::class, 'reviewLeave'])->whereNumber('leave');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal')->group(function () {
                Route::get('/staff', [StaffController::class, 'index']);
                Route::get('/staff/create', [StaffController::class, 'create']);
                Route::post('/staff', [StaffController::class, 'store']);
                Route::get('/staff/{staff}', [StaffController::class, 'show'])->whereNumber('staff');
                Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->whereNumber('staff');
                Route::put('/staff/{staff}', [StaffController::class, 'update'])->whereNumber('staff');
                Route::post('/staff/{staff}/status', [StaffController::class, 'toggleStatus'])->whereNumber('staff');

                Route::get('/academic', [AcademicController::class, 'index']);
                Route::post('/academic/sessions', [AcademicController::class, 'storeSession']);
                Route::post('/academic/terms', [AcademicController::class, 'storeTerm']);
                Route::post('/academic/classes', [AcademicController::class, 'storeClass']);
                Route::post('/academic/subjects', [AcademicController::class, 'storeSubject']);

                Route::get('/assignments', [AssignmentController::class, 'index']);
                Route::post('/assignments', [AssignmentController::class, 'store']);
            });


            Route::middleware(RoleMiddleware::class . ':school_admin,principal,bursar')->group(function () {
                Route::get('/payroll', [PayrollController::class, 'index']);
                Route::post('/payroll/components', [PayrollController::class, 'storeComponent']);
                Route::post('/payroll/assignments', [PayrollController::class, 'storeAssignment']);
                Route::post('/payroll/periods', [PayrollController::class, 'storePeriod']);
                Route::get('/payroll/{period}', [PayrollController::class, 'show'])->whereNumber('period');
                Route::post('/payroll/{period}/generate', [PayrollController::class, 'generate'])->whereNumber('period');
                Route::post('/payroll/{period}/approve', [PayrollController::class, 'approve'])->whereNumber('period');
                Route::post('/payroll/{period}/paid', [PayrollController::class, 'markPaid'])->whereNumber('period');
            });

            Route::middleware(RoleMiddleware::class . ':principal,vice_principal,dean,hod,class_teacher,subject_teacher,bursar,school_admin')->group(function () {
                Route::get('/payroll/payslips', [PayrollController::class, 'payslips']);
                Route::get('/payroll/payslips/{item}', [PayrollController::class, 'payslip'])->whereNumber('item');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,bursar')->group(function () {
                Route::get('/fees', [FeeController::class, 'index']);
                Route::get('/fees/overdue', [FeeController::class, 'overdueInvoices']);
                Route::get('/fees/aging', [FeeController::class, 'invoiceAging']);
                Route::get('/fees/reconciliation', [FeeController::class, 'reconciliation']);
                Route::get('/fees/reconciliation/export', [FeeController::class, 'exportReconciliation']);
                Route::get('/fees/items/create', [FeeController::class, 'createFeeItem']);
                Route::post('/fees/items', [FeeController::class, 'storeFeeItem']);
                Route::get('/fees/invoices/create', [FeeController::class, 'createInvoice']);
                Route::post('/fees/invoices', [FeeController::class, 'storeInvoice']);
                Route::get('/fees/invoices/{invoice}', [FeeController::class, 'showInvoice'])->whereNumber('invoice');
                Route::post('/fees/invoices/{invoice}/payments', [FeeController::class, 'storePayment'])->whereNumber('invoice');
                Route::post('/fees/payments/{payment}/refunds', [FeeController::class, 'requestRefund'])->whereNumber('payment');
                Route::get('/fees/receipts/{payment}', [FeeController::class, 'receipt'])->whereNumber('payment');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal')->group(function () {
                Route::post('/fees/refunds/{refund}/approve', [FeeController::class, 'approveRefund'])->whereNumber('refund');
                Route::post('/fees/refunds/{refund}/reject', [FeeController::class, 'rejectRefund'])->whereNumber('refund');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,bursar')->group(function () {
                Route::post('/fees/refunds/{refund}/settle', [FeeController::class, 'settleRefund'])->whereNumber('refund');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,bursar,librarian')->group(function () {
                Route::get('/library', [LibraryController::class, 'index']);
                Route::post('/library/categories', [LibraryController::class, 'storeCategory']);
                Route::post('/library/books', [LibraryController::class, 'storeBook']);
                Route::post('/library/loans', [LibraryController::class, 'issue']);
                Route::post('/library/loans/{loan}/return', [LibraryController::class, 'returnCopy'])->whereNumber('loan');
                Route::post('/library/loans/{loan}/renew', [LibraryController::class, 'renew'])->whereNumber('loan');
                Route::post('/library/reservations', [LibraryController::class, 'reserve']);
            });


            Route::middleware(RoleMiddleware::class . ':school_admin,principal,bursar')->group(function () {
                Route::get('/inventory', [InventoryController::class, 'index']);
                Route::post('/inventory/categories', [InventoryController::class, 'storeCategory']);
                Route::post('/inventory/locations', [InventoryController::class, 'storeLocation']);
                Route::post('/inventory/suppliers', [InventoryController::class, 'storeSupplier']);
                Route::post('/inventory/items', [InventoryController::class, 'storeItem']);
                Route::post('/inventory/movements', [InventoryController::class, 'adjustStock']);
                Route::post('/inventory/purchase-requests', [InventoryController::class, 'requestPurchase']);
                Route::post('/inventory/purchase-requests/{purchaseRequest}/decision', [InventoryController::class, 'decidePurchaseRequest'])->whereNumber('purchaseRequest');
                Route::post('/inventory/purchase-orders', [InventoryController::class, 'createOrder']);
                Route::post('/inventory/purchase-orders/{purchaseOrder}/receive', [InventoryController::class, 'receiveOrder'])->whereNumber('purchaseOrder');
                Route::post('/inventory/assets', [InventoryController::class, 'storeAsset']);
                Route::post('/inventory/assets/{asset}/status', [InventoryController::class, 'updateAssetStatus'])->whereNumber('asset');
                Route::post('/inventory/assets/{asset}/maintenance', [InventoryController::class, 'storeMaintenance'])->whereNumber('asset');
            });


            Route::middleware(RoleMiddleware::class . ':school_admin,principal,bursar')->group(function () {
                Route::get('/transport', [TransportController::class, 'index']);
                Route::post('/transport/vehicles', [TransportController::class, 'storeVehicle']);
                Route::post('/transport/stops', [TransportController::class, 'storeStop']);
                Route::post('/transport/routes', [TransportController::class, 'storeRoute']);
                Route::post('/transport/assignments', [TransportController::class, 'assignStudent']);
                Route::post('/transport/assignments/{assignment}/end', [TransportController::class, 'endAssignment'])->whereNumber('assignment');
                Route::post('/transport/documents', [TransportController::class, 'storeDocument']);
                Route::post('/transport/trips', [TransportController::class, 'storeTrip']);
                Route::post('/transport/trips/{trip}/attendance', [TransportController::class, 'recordAttendance'])->whereNumber('trip');
                Route::post('/transport/incidents', [TransportController::class, 'storeIncident']);
                Route::post('/transport/incidents/{incident}', [TransportController::class, 'updateIncident'])->whereNumber('incident');
                Route::post('/transport/maintenance', [TransportController::class, 'storeMaintenance']);
            });
            Route::middleware(RoleMiddleware::class . ':parent')->get('/transport/my-children', [TransportController::class, 'parentIndex']);

            Route::get('/timetable', [TimetableController::class, 'index'])
                ->middleware(RoleMiddleware::class . ':school_admin,principal,vice_principal,dean,hod,class_teacher,subject_teacher');
            Route::middleware(RoleMiddleware::class . ':school_admin,principal')->group(function () {
                Route::get('/timetable/create', [TimetableController::class, 'create']);
                Route::post('/timetable', [TimetableController::class, 'store']);
                Route::delete('/timetable/{entry}', [TimetableController::class, 'destroy'])->whereNumber('entry');
            });

            Route::middleware(RoleMiddleware::class . ':school_admin,principal,dean,hod,class_teacher')->group(function () {
                Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
                Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
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
