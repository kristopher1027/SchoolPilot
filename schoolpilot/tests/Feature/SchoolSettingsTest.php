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

class SchoolSettingsTest extends TestCase
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
        $this->databaseName = 'settings-' . $token . '.sqlite';
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
            'id' => 'settings-tenant',
            'data' => json_encode(['slug' => 'settings-test', 'status' => 'active', 'db_name' => $this->databaseName]),
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
        $adminId = DB::connection('tenant_sqlite')->table('users')->insertGetId([
            'name' => 'Settings Administrator',
            'email' => 'settings-admin@example.test',
            'password' => Hash::make('Secure-Test-Password-2026'),
            'role' => 'school_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant_sqlite')->table('settings')->insert([
            'key' => 'school_name',
            'value' => 'Original School',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail($adminId), 'school');
        $this->withSession(['_school_authenticated_slug' => 'settings-test']);
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

    public function test_school_admin_can_view_and_update_school_profile(): void
    {
        $this->get('/school/settings-test/settings')
            ->assertOk()
            ->assertSee('School Settings')
            ->assertSee('Original School');

        $this->put('/school/settings-test/settings', [
            'school_name' => 'Bright Future Academy',
            'motto' => 'Learning for life',
            'school_type' => 'Primary & Secondary',
            'email' => 'office@brightfuture.test',
            'phone' => '+2348012345678',
            'address' => 'Ibadan, Oyo State',
            'website' => 'https://brightfuture.test',
        ])->assertRedirect('/school/settings-test/settings')
          ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'school_name', 'value' => 'Bright Future Academy'], 'tenant_sqlite');
        $this->assertDatabaseHas('settings', ['key' => 'motto', 'value' => 'Learning for life'], 'tenant_sqlite');
    }

    public function test_required_name_and_email_validation_are_enforced(): void
    {
        $this->from('/school/settings-test/settings')->put('/school/settings-test/settings', [
            'school_name' => '',
            'email' => 'not-an-email',
        ])->assertRedirect('/school/settings-test/settings')
          ->assertSessionHasErrors(['school_name', 'email']);
    }

    public function test_non_admin_cannot_access_school_settings(): void
    {
        Auth::guard('school')->logout();
        $teacher = User::query()->create([
            'name' => 'Teacher',
            'email' => 'teacher@example.test',
            'password' => Hash::make('Secure-Test-Password-2026'),
            'role' => 'subject_teacher',
            'is_active' => true,
        ]);
        $this->actingAs($teacher, 'school');

        $this->get('/school/settings-test/settings')->assertForbidden();
    }
}
