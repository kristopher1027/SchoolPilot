<?php

namespace Tests\Feature;

use App\Models\Tenant\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentLifecycleWorkflowTest extends TestCase
{
    private string $tenantDirectory;
    private string $databaseName;
    private string $previousCentralDatabase;
    private string $previousTenantDatabase;
    private string $previousDefaultConnection;
    private string $previousTenantDirectory;
    private bool $createdTenantDirectory = false;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $token = bin2hex(random_bytes(6));
        $this->tenantDirectory = database_path('tenants');
        if (! is_dir($this->tenantDirectory)) {
            mkdir($this->tenantDirectory, 0700, true);
            $this->createdTenantDirectory = true;
        }
        $this->databaseName = 'student-lifecycle-' . $token . '.sqlite';
        $this->previousCentralDatabase = (string) config('database.connections.central_sqlite.database');
        $this->previousTenantDatabase = (string) config('database.connections.tenant_sqlite.database');
        $this->previousDefaultConnection = DB::getDefaultConnection();
        $this->previousTenantDirectory = (string) config('tenancy.database.sqlite.path');
        Config::set('tenancy.database.sqlite.path', $this->tenantDirectory);
        Config::set('session.driver', 'array');
        Config::set('database.connections.central_sqlite.database', ':memory:');
        DB::purge('central_sqlite');

        Schema::connection('central_sqlite')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->json('data')->nullable();
            $table->timestamps();
        });
        DB::connection('central_sqlite')->table('tenants')->insert([
            'id' => 'student-lifecycle-tenant',
            'data' => json_encode(['slug' => 'student-lifecycle-test', 'status' => 'active', 'db_name' => $this->databaseName]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = $this->tenantDirectory . '/' . $this->databaseName;
        touch($path);
        Config::set('database.connections.tenant_sqlite.database', $path);
        DB::purge('tenant_sqlite');

        Schema::connection('tenant_sqlite')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('sections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('classes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('section_id');
            $table->string('name');
            $table->integer('level');
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('admission_no')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('gender');
            $table->date('date_of_birth');
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->date('date_admitted');
            $table->string('status')->default('active');
            $table->text('address')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('genotype')->nullable();
            $table->text('allergies')->nullable();
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('parents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('occupation')->nullable();
            $table->text('address')->nullable();
            $table->string('relationship')->nullable();
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('student_parents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('parent_id');
            $table->boolean('is_primary')->default(false);
            $table->string('relationship', 100)->nullable();
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('can_pickup')->default(false);
            $table->timestamps();
            $table->unique(['student_id', 'parent_id']);
        });
        Schema::connection('tenant_sqlite')->create('staff', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('staff_no')->unique();
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('class_teachers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('class_id');
            $table->date('date');
            $table->string('status');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'date']);
        });
        Schema::connection('tenant_sqlite')->create('student_enrollment_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('from_section_id')->nullable();
            $table->unsignedBigInteger('to_section_id')->nullable();
            $table->unsignedBigInteger('from_class_id')->nullable();
            $table->unsignedBigInteger('to_class_id')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('change_type');
            $table->text('reason');
            $table->date('effective_on');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });

        Config::set('database.default', 'tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');

        $this->adminId = DB::connection('tenant_sqlite')->table('users')->insertGetId([
            'name' => 'Student Administrator',
            'email' => 'student-admin@example.test',
            'password' => Hash::make('Secure-Test-Password-2026'),
            'role' => 'school_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail($this->adminId), 'school');
        $this->withSession(['_school_authenticated_slug' => 'student-lifecycle-test']);
    }

    protected function tearDown(): void
    {
        Auth::forgetGuards();
        DB::purge('tenant_sqlite');
        DB::purge('central_sqlite');
        Config::set('database.connections.central_sqlite.database', $this->previousCentralDatabase);
        Config::set('database.connections.tenant_sqlite.database', $this->previousTenantDatabase);
        Config::set('tenancy.database.sqlite.path', $this->previousTenantDirectory);
        DB::setDefaultConnection($this->previousDefaultConnection);

        @unlink($this->tenantDirectory . '/' . $this->databaseName);
        if ($this->createdTenantDirectory) {
            @rmdir($this->tenantDirectory);
        }

        parent::tearDown();
    }

    private function createStudent(int $sectionId, int $classId): int
    {
        return DB::connection('tenant_sqlite')->table('students')->insertGetId([
            'admission_no' => 'STU-001',
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'gender' => 'female',
            'date_of_birth' => '2012-04-10',
            'section_id' => $sectionId,
            'class_id' => $classId,
            'date_admitted' => '2024-09-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    public function test_guardian_link_records_student_specific_relationship_and_contact_permissions(): void
    {
        $sectionId = DB::table('sections')->insertGetId(['name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now()]);
        $classId = DB::table('classes')->insertGetId(['section_id' => $sectionId, 'name' => 'Primary 1', 'level' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $studentId = $this->createStudent($sectionId, $classId);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Ada Okafor',
            'email' => 'guardian@example.test',
            'password' => Hash::make('Secure-Test-Password-2026'),
            'role' => 'parent',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $guardianId = DB::table('parents')->insertGetId([
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/school/student-lifecycle-test/students/' . $studentId . '/guardians', [
            'guardian_id' => $guardianId,
            'relationship' => 'Mother',
            'is_primary' => '1',
            'is_emergency_contact' => '1',
            'can_pickup' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('student_parents', [
            'student_id' => $studentId,
            'parent_id' => $guardianId,
            'relationship' => 'Mother',
            'is_primary' => 1,
            'is_emergency_contact' => 1,
            'can_pickup' => 1,
        ], 'tenant_sqlite');
    }

    public function test_student_has_only_one_primary_and_emergency_guardian(): void
    {
        $sectionId = DB::table('sections')->insertGetId(['name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now()]);
        $classId = DB::table('classes')->insertGetId(['section_id' => $sectionId, 'name' => 'Primary 1', 'level' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $studentId = $this->createStudent($sectionId, $classId);
        $guardianIds = [];
        foreach (['first', 'second'] as $suffix) {
            $userId = DB::table('users')->insertGetId([
                'name' => ucfirst($suffix) . ' Guardian',
                'email' => $suffix . '-guardian@example.test',
                'password' => Hash::make('Secure-Test-Password-2026'),
                'role' => 'parent',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $guardianIds[] = DB::table('parents')->insertGetId([
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($guardianIds as $index => $guardianId) {
            $this->post('/school/student-lifecycle-test/students/' . $studentId . '/guardians', [
                'guardian_id' => $guardianId,
                'relationship' => $index === 0 ? 'Mother' : 'Father',
                'is_primary' => '1',
                'is_emergency_contact' => '1',
            ])->assertRedirect('/school/student-lifecycle-test/students/' . $studentId)
              ->assertSessionHasNoErrors()
              ->assertSessionHas('success', 'Guardian linked to student.');
        }

        $this->assertDatabaseHas('student_parents', ['student_id' => $studentId, 'parent_id' => $guardianIds[0], 'is_primary' => 0, 'is_emergency_contact' => 0], 'tenant_sqlite');
        $this->assertDatabaseHas('student_parents', ['student_id' => $studentId, 'parent_id' => $guardianIds[1], 'is_primary' => 1, 'is_emergency_contact' => 1], 'tenant_sqlite');
    }

    public function test_guardian_can_link_to_multiple_students_but_each_student_is_limited_to_two_guardians(): void
    {
        $sectionOne = DB::connection('tenant_sqlite')->table('sections')->insertGetId([
            'name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classOne = DB::connection('tenant_sqlite')->table('classes')->insertGetId([
            'section_id' => $sectionOne, 'name' => 'Primary 1', 'level' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentOne = $this->createStudent($sectionOne, $classOne);

        $sectionTwo = DB::connection('tenant_sqlite')->table('sections')->insertGetId([
            'name' => 'Secondary', 'code' => 'SEC', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classTwo = DB::connection('tenant_sqlite')->table('classes')->insertGetId([
            'section_id' => $sectionTwo, 'name' => 'JSS 1', 'level' => 7, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentTwo = DB::connection('tenant_sqlite')->table('students')->insertGetId([
            'admission_no' => 'STU-002', 'first_name' => 'Bola', 'last_name' => 'Okafor',
            'gender' => 'female', 'date_of_birth' => '2011-05-12', 'section_id' => $sectionTwo,
            'class_id' => $classTwo, 'date_admitted' => '2024-09-01', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $createGuardian = function (string $name, string $email): int {
            $userId = DB::connection('tenant_sqlite')->table('users')->insertGetId([
                'name' => $name, 'email' => $email,
                'password' => Hash::make('Secure-Test-Password-2026'),
                'role' => 'parent', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::connection('tenant_sqlite')->table('parents')->insertGetId([
                'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        $sharedGuardian = $createGuardian('Shared Guardian', 'shared@example.test');
        $secondGuardian = $createGuardian('Second Guardian', 'second@example.test');
        $thirdGuardian = $createGuardian('Third Guardian', 'third@example.test');

        $this->post('/school/student-lifecycle-test/students/' . $studentOne . '/guardians', [
            'guardian_id' => $sharedGuardian, 'relationship' => 'Mother', 'is_primary' => '1',
        ])->assertSessionHas('success', 'Guardian linked to student.');

        $this->post('/school/student-lifecycle-test/students/' . $studentOne . '/guardians', [
            'guardian_id' => $secondGuardian, 'relationship' => 'Father', 'is_emergency_contact' => '1',
        ])->assertSessionHas('success', 'Guardian linked to student.');

        $this->post('/school/student-lifecycle-test/students/' . $studentTwo . '/guardians', [
            'guardian_id' => $sharedGuardian, 'relationship' => 'Aunt', 'is_emergency_contact' => '1',
        ])->assertSessionHas('success', 'Guardian linked to student.');

        $this->post('/school/student-lifecycle-test/students/' . $studentOne . '/guardians', [
            'guardian_id' => $thirdGuardian, 'relationship' => 'Uncle',
        ])->assertSessionHasErrors('guardian_id');

        $this->assertDatabaseHas('student_parents', [
            'student_id' => $studentOne, 'parent_id' => $sharedGuardian,
            'relationship' => 'Mother', 'is_primary' => 1,
        ], 'tenant_sqlite');
        $this->assertDatabaseHas('student_parents', [
            'student_id' => $studentTwo, 'parent_id' => $sharedGuardian,
            'relationship' => 'Aunt', 'is_primary' => 0, 'is_emergency_contact' => 1,
        ], 'tenant_sqlite');
        $this->assertDatabaseMissing('student_parents', [
            'student_id' => $studentOne, 'parent_id' => $thirdGuardian,
        ], 'tenant_sqlite');
        $this->assertSame(2, DB::connection('tenant_sqlite')->table('student_parents')
            ->where('student_id', $studentOne)->count());
    }

    public function test_csv_import_creates_students_and_enrollment_history(): void
    {
        $sectionId = DB::table('sections')->insertGetId(['name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now()]);
        $csv = "admission_no,first_name,last_name,middle_name,gender,date_of_birth,section_id,class_id,date_admitted,address,blood_group,genotype,allergies\n"
            . "CSV-001,Ada,Okafor,,female,2012-04-10,{$sectionId},,2026-09-01,,,,\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $this->post('/school/student-lifecycle-test/students/import', ['file' => $file])
            ->assertRedirect('/school/student-lifecycle-test/students')
            ->assertSessionHas('success', '1 student(s) imported.');

        $this->assertDatabaseHas('students', ['admission_no' => 'CSV-001', 'first_name' => 'Ada'], 'tenant_sqlite');
        $this->assertDatabaseHas('student_enrollment_history', [
            'change_type' => 'enrollment',
            'reason' => 'Initial enrollment via CSV import',
            'changed_by' => $this->adminId,
        ], 'tenant_sqlite');
    }

    public function test_csv_import_skips_duplicate_and_invalid_rows_without_creating_them(): void
    {
        $sectionId = DB::table('sections')->insertGetId(['name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now()]);
        $csv = "admission_no,first_name,last_name,gender,date_of_birth,section_id,date_admitted\n"
            . "DUP-001,Ada,Okafor,female,2012-04-10,{$sectionId},2026-09-01\n"
            . "DUP-001,Eva,Okafor,female,2012-04-10,{$sectionId},2026-09-01\n"
            . "BAD-001,Sam,Okafor,unknown,2012-04-10,{$sectionId},2026-09-01\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $this->post('/school/student-lifecycle-test/students/import', ['file' => $file])
            ->assertRedirect('/school/student-lifecycle-test/students')
            ->assertSessionHas('success', '1 student(s) imported.')
            ->assertSessionHas('import_errors');

        $this->assertDatabaseHas('students', ['admission_no' => 'DUP-001'], 'tenant_sqlite');
        $this->assertDatabaseMissing('students', ['admission_no' => 'BAD-001'], 'tenant_sqlite');
        $this->assertDatabaseCount('student_enrollment_history', 1, 'tenant_sqlite');
    }

    public function test_student_csv_export_returns_a_csv_download(): void
    {
        $sectionId = DB::table('sections')->insertGetId(['name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now()]);
        $classId = DB::table('classes')->insertGetId(['section_id' => $sectionId, 'name' => 'Primary 1', 'level' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->createStudent($sectionId, $classId);

        $this->get('/school/student-lifecycle-test/students/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
    }

    public function test_promotion_updates_placement_and_records_auditable_history(): void
    {
        $sectionId = DB::table('sections')->insertGetId(['name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now()]);
        $firstClass = DB::table('classes')->insertGetId(['section_id' => $sectionId, 'name' => 'Primary 1', 'level' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $nextClass = DB::table('classes')->insertGetId(['section_id' => $sectionId, 'name' => 'Primary 2', 'level' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $studentId = $this->createStudent($sectionId, $firstClass);

        $this->post('/school/student-lifecycle-test/students/' . $studentId . '/lifecycle', [
            'section_id' => $sectionId,
            'class_id' => $nextClass,
            'status' => 'active',
            'effective_on' => '2026-09-01',
            'reason' => 'Promoted after successful completion of Primary 1.',
        ])->assertRedirect('/school/student-lifecycle-test/students/' . $studentId)
          ->assertSessionHas('success');

        $this->assertDatabaseHas('students', ['id' => $studentId, 'class_id' => $nextClass, 'status' => 'active'], 'tenant_sqlite');
        $this->assertDatabaseHas('student_enrollment_history', [
            'student_id' => $studentId,
            'from_class_id' => $firstClass,
            'to_class_id' => $nextClass,
            'change_type' => 'promotion',
            'reason' => 'Promoted after successful completion of Primary 1.',
            'changed_by' => $this->adminId,
        ], 'tenant_sqlite');
    }

    public function test_lifecycle_change_rejects_a_class_from_another_section(): void
    {
        $firstSection = DB::table('sections')->insertGetId(['name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now()]);
        $otherSection = DB::table('sections')->insertGetId(['name' => 'Secondary', 'code' => 'SEC', 'created_at' => now(), 'updated_at' => now()]);
        $firstClass = DB::table('classes')->insertGetId(['section_id' => $firstSection, 'name' => 'Primary 1', 'level' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $wrongClass = DB::table('classes')->insertGetId(['section_id' => $otherSection, 'name' => 'JSS 1', 'level' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $studentId = $this->createStudent($firstSection, $firstClass);

        $this->from('/school/student-lifecycle-test/students/' . $studentId)->post('/school/student-lifecycle-test/students/' . $studentId . '/lifecycle', [
            'section_id' => $firstSection,
            'class_id' => $wrongClass,
            'status' => 'active',
            'effective_on' => '2026-09-01',
            'reason' => 'Invalid placement test.',
        ])->assertRedirect('/school/student-lifecycle-test/students/' . $studentId)
          ->assertSessionHasErrors(['class_id']);

        $this->assertDatabaseHas('students', ['id' => $studentId, 'class_id' => $firstClass], 'tenant_sqlite');
        $this->assertDatabaseCount('student_enrollment_history', 0, 'tenant_sqlite');
    }

    public function test_attendance_register_requires_explicit_status_and_tracks_unmarked_students(): void
    {
        $sectionId = DB::table('sections')->insertGetId([
            'name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classId = DB::table('classes')->insertGetId([
            'section_id' => $sectionId, 'name' => 'Primary 1', 'level' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentId = $this->createStudent($sectionId, $classId);
        $date = today()->toDateString();

        $this->get('/school/student-lifecycle-test/attendance?class_id=' . $classId . '&date=' . $date)
            ->assertOk()
            ->assertSee('Unmarked')
            ->assertSee('Select status');

        $this->post('/school/student-lifecycle-test/attendance', [
            'class_id' => $classId,
            'date' => $date,
            'statuses' => [$studentId => 'present'],
        ])->assertRedirect('/school/student-lifecycle-test/attendance?class_id=' . $classId . '&date=' . $date)
          ->assertSessionHas('success', 'Attendance saved successfully.');

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $studentId,
            'class_id' => $classId,
            'date' => $date,
            'status' => 'present',
        ], 'tenant_sqlite');

        $this->get('/school/student-lifecycle-test/attendance?class_id=' . $classId . '&date=' . $date)
            ->assertOk()
            ->assertSee('Present')
            ->assertSee('Unmarked');
    }



    public function test_attendance_submission_rejects_a_partial_class_register(): void
    {
        $sectionId = DB::table('sections')->insertGetId([
            'name' => 'Primary', 'code' => 'PRI', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classId = DB::table('classes')->insertGetId([
            'section_id' => $sectionId, 'name' => 'Primary 1', 'level' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $firstStudentId = $this->createStudent($sectionId, $classId);
        $secondStudentId = DB::table('students')->insertGetId([
            'admission_no' => 'STU-002',
            'first_name' => 'Bola',
            'last_name' => 'Okafor',
            'gender' => 'female',
            'date_of_birth' => '2012-05-10',
            'section_id' => $sectionId,
            'class_id' => $classId,
            'date_admitted' => '2024-09-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from('/school/student-lifecycle-test/attendance?class_id=' . $classId)
            ->post('/school/student-lifecycle-test/attendance', [
                'class_id' => $classId,
                'date' => today()->toDateString(),
                'statuses' => [$firstStudentId => 'present'],
            ])
            ->assertRedirect('/school/student-lifecycle-test/attendance?class_id=' . $classId)
            ->assertSessionHasErrors(['statuses']);

        $this->assertDatabaseCount('attendance_records', 0, 'tenant_sqlite');
    }


}
