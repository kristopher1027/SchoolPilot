<?php

namespace Tests\Feature;

use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicCalendarTest extends TestCase
{
    private string $tenantDirectory;
    private string $databaseName;
    private string $previousCentralDatabase;
    private string $previousTenantDatabase;
    private string $previousDefaultConnection;
    private string $previousTenantDirectory;
    private bool $createdTenantDirectory = false;

    protected function setUp(): void
    {
        parent::setUp();

        $token = bin2hex(random_bytes(6));
        $this->tenantDirectory = database_path('tenants');
        if (! is_dir($this->tenantDirectory)) {
            mkdir($this->tenantDirectory, 0700, true);
            $this->createdTenantDirectory = true;
        }
        $this->databaseName = 'academic-' . $token . '.sqlite';
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
            'id' => 'academic-tenant',
            'data' => json_encode(['slug' => 'academic-test', 'status' => 'active', 'db_name' => $this->databaseName]),
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
        Schema::connection('tenant_sqlite')->create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->timestamps();
        });

        $adminId = DB::connection('tenant_sqlite')->table('users')->insertGetId([
            'name' => 'Academic Administrator',
            'email' => 'academic-admin@example.test',
            'password' => Hash::make('Secure-Test-Password-2026'),
            'role' => 'school_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs(User::query()->findOrFail($adminId), 'school');
        $this->withSession(['_school_authenticated_slug' => 'academic-test']);
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

    public function test_academic_page_uses_the_current_school_slug_in_form_actions(): void
    {
        $this->get('/school/academic-test/academic')
            ->assertOk()
            ->assertSee('/school/academic-test/academic/sessions')
            ->assertDontSee('/school/greenfield/academic/sessions');
    }

    public function test_session_dates_must_be_valid_and_session_names_unique(): void
    {
        $this->from('/school/academic-test/academic')->post('/school/academic-test/academic/sessions', [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2026-08-31',
        ])->assertRedirect('/school/academic-test/academic')
          ->assertSessionHasErrors(['end_date']);

        AcademicSession::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'is_current' => false,
        ]);

        $this->from('/school/academic-test/academic')->post('/school/academic-test/academic/sessions', [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
        ])->assertRedirect('/school/academic-test/academic')
          ->assertSessionHasErrors(['name']);
    }

    public function test_term_must_fit_inside_session_and_must_not_overlap(): void
    {
        $session = AcademicSession::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'is_current' => false,
        ]);

        $this->from('/school/academic-test/academic')->post('/school/academic-test/academic/terms', [
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'start_date' => '2026-08-20',
            'end_date' => '2026-12-10',
        ])->assertRedirect('/school/academic-test/academic')
          ->assertSessionHasErrors(['start_date']);

        $this->post('/school/academic-test/academic/terms', [
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-10',
        ])->assertRedirect('/school/academic-test/academic')
          ->assertSessionHasNoErrors();

        $this->from('/school/academic-test/academic')->post('/school/academic-test/academic/terms', [
            'academic_session_id' => $session->id,
            'name' => 'Second Term',
            'start_date' => '2026-12-01',
            'end_date' => '2027-03-20',
        ])->assertRedirect('/school/academic-test/academic')
          ->assertSessionHasErrors(['start_date']);
    }

    public function test_only_one_academic_session_and_one_term_can_be_current(): void
    {
        $first = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-07-31',
            'is_current' => true,
        ]);
        $second = AcademicSession::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'is_current' => false,
        ]);
        $firstTerm = Term::create([
            'academic_session_id' => $first->id,
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-10',
            'is_current' => true,
        ]);
        $secondTerm = Term::create([
            'academic_session_id' => $second->id,
            'name' => 'First Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-10',
            'is_current' => false,
        ]);

        $this->post('/school/academic-test/academic/sessions/' . $second->id . '/current')
            ->assertRedirect();

        $this->assertDatabaseHas('academic_sessions', ['id' => $first->id, 'is_current' => 0], 'tenant_sqlite');
        $this->assertDatabaseHas('academic_sessions', ['id' => $second->id, 'is_current' => 1], 'tenant_sqlite');
        $this->assertDatabaseHas('terms', ['id' => $firstTerm->id, 'is_current' => 0], 'tenant_sqlite');

        $this->post('/school/academic-test/academic/terms/' . $secondTerm->id . '/current')
            ->assertRedirect();

        $this->assertDatabaseHas('terms', ['id' => $secondTerm->id, 'is_current' => 1], 'tenant_sqlite');
    }

    public function test_term_cannot_be_made_current_when_its_session_is_not_current(): void
    {
        $session = AcademicSession::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'is_current' => false,
        ]);
        $term = Term::create([
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-10',
            'is_current' => false,
        ]);

        $this->from('/school/academic-test/academic')->post('/school/academic-test/academic/terms/' . $term->id . '/current')
            ->assertRedirect('/school/academic-test/academic')
            ->assertSessionHasErrors(['term']);
    }
}
