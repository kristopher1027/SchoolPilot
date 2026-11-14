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

class TenantDashboardIdentityTest extends TestCase
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

    public function test_shared_navigation_and_dashboard_use_the_active_tenant_identity(): void
    {
        $this->get('/school/results-test/results')
            ->assertOk()
            ->assertSee('Sunrise Academy')
            ->assertSee('/school/results-test/results/class')
            ->assertSee('/school/results-test/dashboard')
            ->assertSee('/school/results-test/students')
            ->assertSee('/school/results-test/logout')
            ->assertSee('Sunrise Academy today.')
            ->assertDontSee('/school/greenfield')
            ->assertDontSee('Greenfield Academy');
    }
}
