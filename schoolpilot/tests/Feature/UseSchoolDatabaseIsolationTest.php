<?php

namespace Tests\Feature;

use App\Http\Middleware\UseSchoolDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Models\Tenant\User;
use Tests\TestCase;

class UseSchoolDatabaseIsolationTest extends TestCase
{
    private string $directory;
    private string $tenantDirectory;
    private string $alphaDatabaseName;
    private string $betaDatabaseName;
    private bool $tenantDirectoryCreated = false;
    private string $centralPath;
    private string $previousCentralPath;
    private string $previousTenantPath;
    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();

        $token = bin2hex(random_bytes(6));
        $this->directory = sys_get_temp_dir() . '/schoolpilot-tenancy-' . $token;
        mkdir($this->directory, 0700, true);

        // The middleware intentionally resolves tenant files beneath database/tenants.
        $this->tenantDirectory = database_path('tenants');
        if (! is_dir($this->tenantDirectory)) {
            mkdir($this->tenantDirectory, 0700, true);
            $this->tenantDirectoryCreated = true;
        }
        $this->alphaDatabaseName = 'test-alpha-' . $token . '.sqlite';
        $this->betaDatabaseName = 'test-beta-' . $token . '.sqlite';

        $this->centralPath = $this->directory . '/central.sqlite';
        touch($this->centralPath);

        $this->previousCentralPath = (string) config('database.connections.central_sqlite.database');
        $this->previousTenantPath = (string) config('database.connections.tenant_sqlite.database');
        $this->previousDefault = DB::getDefaultConnection();

        Config::set('database.connections.central_sqlite.database', $this->centralPath);
        DB::purge('central_sqlite');

        Schema::connection('central_sqlite')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::connection('central_sqlite')->create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Config::set('session.driver', 'database');
        Config::set('session.connection', 'central_sqlite');
        Config::set('session.table', 'sessions');

        $this->createTenantDatabase($this->alphaDatabaseName, 'Alpha School');
        $this->createTenantDatabase($this->betaDatabaseName, 'Beta School');

        DB::connection('central_sqlite')->table('tenants')->insert([
            [
                'id' => 'alpha-id',
                'data' => json_encode(['slug' => 'alpha', 'status' => 'active', 'db_name' => $this->alphaDatabaseName]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'beta-id',
                'data' => json_encode(['slug' => 'beta', 'status' => 'active', 'db_name' => $this->betaDatabaseName]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'suspended-id',
                'data' => json_encode(['slug' => 'suspended', 'status' => 'suspended', 'db_name' => $this->alphaDatabaseName]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Route::middleware('web')->get('/_tenant-isolation/{school}', function () {
            if (request()->boolean('login')) {
                Auth::guard('school')->login(User::query()->findOrFail(1));
            }

            return response()->json([
                'marker' => DB::table('tenant_marker')->value('marker'),
                'user_id' => Auth::guard('school')->id(),
            ]);
        });
    }

    protected function tearDown(): void
    {
        DB::purge('central_sqlite');
        DB::purge('tenant_sqlite');
        Config::set('database.connections.central_sqlite.database', $this->previousCentralPath);
        Config::set('database.connections.tenant_sqlite.database', $this->previousTenantPath);
        DB::setDefaultConnection($this->previousDefault);

        @unlink($this->tenantDirectory . '/' . $this->alphaDatabaseName);
        @unlink($this->tenantDirectory . '/' . $this->betaDatabaseName);
        if ($this->tenantDirectoryCreated) {
            @rmdir($this->tenantDirectory);
        }
        foreach (glob($this->directory . '/*.sqlite') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);

        parent::tearDown();
    }

    public function test_each_school_request_reads_only_its_own_sqlite_database(): void
    {
        $this->getJson('/_tenant-isolation/alpha')
            ->assertOk()
            ->assertExactJson(['marker' => 'Alpha School', 'user_id' => null]);

        $this->getJson('/_tenant-isolation/beta')
            ->assertOk()
            ->assertExactJson(['marker' => 'Beta School', 'user_id' => null]);
    }

    public function test_authenticated_session_cannot_be_reused_for_another_school(): void
    {
        $this->getJson('/_tenant-isolation/alpha?login=1')
            ->assertOk()
            ->assertJsonPath('user_id', 1);

        $this->getJson('/_tenant-isolation/beta')
            ->assertOk()
            ->assertJsonPath('marker', 'Beta School')
            ->assertJsonPath('user_id', null);
    }

    public function test_unknown_school_slug_is_rejected(): void
    {
        $this->get('/_tenant-isolation/not-registered')->assertNotFound();
    }

    public function test_suspended_school_is_blocked_before_tenant_database_access(): void
    {
        $this->get('/_tenant-isolation/suspended')->assertForbidden();
    }

    public function test_invalid_school_slug_is_rejected(): void
    {
        $this->get('/_tenant-isolation/../alpha')->assertNotFound();
    }

    private function createTenantDatabase(string $filename, string $marker): void
    {
        $path = $this->tenantDirectory . '/' . $filename;
        touch($path);

        Config::set('database.connections.tenant_sqlite.database', $path);
        DB::purge('tenant_sqlite');

        Schema::connection('tenant_sqlite')->create('tenant_marker', function (Blueprint $table): void {
            $table->string('marker');
        });
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

        DB::connection('tenant_sqlite')->table('tenant_marker')->insert(['marker' => $marker]);
        DB::connection('tenant_sqlite')->table('users')->insert([
            'id' => 1,
            'name' => $marker . ' Administrator',
            'email' => strtolower(str_replace(' ', '.', $marker)) . '@example.test',
            'password' => bcrypt('Tenant-Test-Password-2026'),
            'role' => 'school_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
