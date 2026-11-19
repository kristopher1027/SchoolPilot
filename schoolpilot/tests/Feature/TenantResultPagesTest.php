<?php

namespace Tests\Feature;

use App\Models\Tenant\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantResultPagesTest extends TestCase
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
        $this->databaseName = 'results-' . $token . '.sqlite';
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
            'id' => 'results-tenant',
            'data' => json_encode(['slug' => 'results-test', 'status' => 'active', 'db_name' => $this->databaseName]),
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
        Schema::connection('tenant_sqlite')->create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('academic_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('terms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('academic_session_id');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
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
            $table->unsignedBigInteger('class_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('parents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('student_parents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('student_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('result_approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('academic_session_id');
            $table->string('status');
            $table->timestamps();
        });

        Config::set('database.default', 'tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');
        $this->adminId = DB::connection('tenant_sqlite')->table('users')->insertGetId([
            'name' => 'Results Administrator',
            'email' => 'results-admin@example.test',
            'password' => Hash::make('Secure-Test-Password-2026'),
            'role' => 'school_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant_sqlite')->table('settings')->insert([
            'key' => 'school_name',
            'value' => 'Sunrise Academy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail($this->adminId), 'school');
        $this->withSession(['_school_authenticated_slug' => 'results-test']);
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

    public function test_parent_cannot_open_an_unrelated_students_report_card(): void
    {
        [$parentUserId, $ownStudentId, $otherStudentId, $termId, $sessionId] = $this->createParentFixture();

        $this->actingAs(User::query()->findOrFail($parentUserId), 'school')
            ->withSession(['_school_authenticated_slug' => 'results-test'])
            ->get("/school/results-test/parent-portal/results/{$otherStudentId}?term_id={$termId}&session_id={$sessionId}")
            ->assertForbidden();
    }

    public function test_parent_cannot_open_an_unpublished_report_card_for_their_child(): void
    {
        [$parentUserId, $studentId, , $termId, $sessionId] = $this->createParentFixture();

        DB::connection('tenant_sqlite')->table('sections')->insert([
            'id' => 1,
            'name' => 'Primary',
            'code' => 'PRI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant_sqlite')->table('classes')->insert([
            'id' => 1,
            'section_id' => 1,
            'name' => 'Primary 1',
            'level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant_sqlite')->table('result_approvals')->insert([
            'student_id' => $studentId,
            'term_id' => $termId,
            'academic_session_id' => $sessionId,
            'status' => 'principal_pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parentUser = User::query()->findOrFail($parentUserId);

        $this->assertSame('parent', $parentUser->role);
        $this->assertTrue((bool) $parentUser->is_active);
        $this->assertTrue(
            DB::connection('tenant_sqlite')->table('student_parents')
                ->join('parents', 'parents.id', '=', 'student_parents.parent_id')
                ->where('parents.user_id', $parentUser->getAuthIdentifier())
                ->where('student_parents.student_id', $studentId)
                ->exists(),
            'The fixture must link the authenticated parent to their own student.'
        );

        $response = $this->actingAs($parentUser, 'school')
            ->withSession(['_school_authenticated_slug' => 'results-test'])
            ->get("/school/results-test/parent-portal/results/{$studentId}?term_id={$termId}&session_id={$sessionId}");

        $this->assertSame(
            404,
            $response->status(),
            'Expected an unpublished report card to be hidden with 404. Response body: ' . $response->getContent()
        );
    }

    private function createParentFixture(): array
    {
        $now = now();
        $sessionId = DB::connection('tenant_sqlite')->table('academic_sessions')->insertGetId([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'is_current' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $termId = DB::connection('tenant_sqlite')->table('terms')->insertGetId([
            'academic_session_id' => $sessionId,
            'name' => 'First Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-18',
            'is_current' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $parentUserId = DB::connection('tenant_sqlite')->table('users')->insertGetId([
            'name' => 'Test Parent',
            'email' => 'parent-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => Hash::make('Secure-Test-Password-2026'),
            'role' => 'parent',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $guardianId = DB::connection('tenant_sqlite')->table('parents')->insertGetId([
            'user_id' => $parentUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ownStudentId = DB::connection('tenant_sqlite')->table('students')->insertGetId([
            'class_id' => 1,
            'first_name' => 'Linked',
            'last_name' => 'Student',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $otherStudentId = DB::connection('tenant_sqlite')->table('students')->insertGetId([
            'class_id' => 1,
            'first_name' => 'Unlinked',
            'last_name' => 'Student',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::connection('tenant_sqlite')->table('student_parents')->insert([
            'parent_id' => $guardianId,
            'student_id' => $ownStudentId,
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$parentUserId, $ownStudentId, $otherStudentId, $termId, $sessionId];
    }

    public function test_results_page_uses_the_active_tenant_url_and_school_profile(): void
    {
        $this->get('/school/results-test/results')
            ->assertOk()
            ->assertSee('Sunrise Academy')
            ->assertSee('/school/results-test/results/class')
            ->assertDontSee('/school/greenfield/results/class');
    }
}
