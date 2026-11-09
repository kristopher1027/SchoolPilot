<?php

namespace Tests\Feature;

use App\Http\Middleware\UseSchoolDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

        Route::middleware(UseSchoolDatabase::class)->get('/_tenant-isolation/{school}', function () {
            return response()->json([
                'marker' => DB::table('tenant_marker')->value('marker'),
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
            ->assertExactJson(['marker' => 'Alpha School']);

        $this->getJson('/_tenant-isolation/beta')
            ->assertOk()
            ->assertExactJson(['marker' => 'Beta School']);
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

        DB::connection('tenant_sqlite')->table('tenant_marker')->insert(['marker' => $marker]);
    }
}
