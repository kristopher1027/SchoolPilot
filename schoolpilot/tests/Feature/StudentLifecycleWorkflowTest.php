<?php

namespace Tests\\Feature;

use App\\Models\\Tenant\\User;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\Config;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\Schema;
use Tests\\TestCase;

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
}
