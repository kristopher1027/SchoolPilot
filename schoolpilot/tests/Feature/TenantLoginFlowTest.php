<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantLoginFlowTest extends TestCase
{
    private string $tenantDirectory;
    private string $alphaDatabase;
    private string $betaDatabase;
    private string $previousCentralDatabase;
    private string $previousTenantDatabase;
    private string $previousDefaultConnection;
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

        $this->alphaDatabase = 'login-alpha-' . $token . '.sqlite';
        $this->betaDatabase = 'login-beta-' . $token . '.sqlite';
        $this->previousCentralDatabase = (string) config('database.connections.central_sqlite.database');
        $this->previousTenantDatabase = (string) config('database.connections.tenant_sqlite.database');
        $this->previousDefaultConnection = DB::getDefaultConnection();

        Config::set('session.driver', 'array');
        Config::set('database.connections.central_sqlite.database', ':memory:');
        DB::purge('central_sqlite');

        Schema::connection('central_sqlite')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        $this->createTenantDatabase($this->alphaDatabase, 'alpha', 'alpha-admin@example.test');
        $this->createTenantDatabase($this->betaDatabase, 'beta', 'beta-admin@example.test');
    }

    protected function tearDown(): void
    {
        DB::purge('tenant_sqlite');
        DB::purge('central_sqlite');

        Config::set('database.connections.central_sqlite.database', $this->previousCentralDatabase);
        Config::set('database.connections.tenant_sqlite.database', $this->previousTenantDatabase);
        DB::setDefaultConnection($this->previousDefaultConnection);

        @unlink($this->tenantDirectory . '/' . $this->alphaDatabase);
        @unlink($this->tenantDirectory . '/' . $this->betaDatabase);
        if ($this->createdTenantDirectory) {
            @rmdir($this->tenantDirectory);
        }

        parent::tearDown();
    }

    public function test_login_redirects_to_the_matching_school_and_keeps_the_session_authenticated(): void
    {
        $this->post('/login', [
            'email' => 'alpha-admin@example.test',
            'password' => 'Secure-Tenant-Password-2026',
        ])->assertRedirect('/school/alpha');

        // Simulate the fresh PHP request lifecycle used by a browser.
        Auth::forgetGuards();

        $this->get('/school/alpha')
            ->assertRedirect('/school/alpha/dashboard');

        $this->get('/school/alpha/dashboard')->assertOk();
    }

    public function test_same_credentials_in_multiple_active_schools_are_rejected_as_ambiguous(): void
    {
        $databasePath = database_path('tenants/' . $this->betaDatabase);
        Config::set('database.connections.tenant_sqlite.database', $databasePath);
        DB::purge('tenant_sqlite');

        DB::connection('tenant_sqlite')->table('users')->insert([
            'name' => 'Duplicate Administrator',
            'email' => 'alpha-admin@example.test',
            'password' => Hash::make('Secure-Tenant-Password-2026'),
            'role' => 'school_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/login', [
            'email' => 'alpha-admin@example.test',
            'password' => 'Secure-Tenant-Password-2026',
        ])->assertRedirect('/login')
          ->assertSessionHasErrors('email');
    }

    private function createTenantDatabase(string $filename, string $slug, string $email): void
    {
        $path = $this->tenantDirectory . '/' . $filename;
        touch($path);

        Config::set('database.connections.tenant_sqlite.database', $path);
        DB::purge('tenant_sqlite');

        Schema::connection('tenant_sqlite')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('school_admin');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::connection('tenant_sqlite')->create('students', function (Blueprint $table): void {
            $table->id();
        });
        Schema::connection('tenant_sqlite')->create('classes', function (Blueprint $table): void {
            $table->id();
        });
        Schema::connection('tenant_sqlite')->create('academic_sessions', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_current')->default(false);
        });

        DB::connection('tenant_sqlite')->table('users')->insert([
            'name' => ucfirst($slug) . ' Administrator',
            'email' => $email,
            'password' => Hash::make('Secure-Tenant-Password-2026'),
            'role' => 'school_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central_sqlite')->table('tenants')->insert([
            'id' => $slug . '-tenant',
            'data' => json_encode([
                'slug' => $slug,
                'status' => 'active',
                'db_name' => $filename,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
