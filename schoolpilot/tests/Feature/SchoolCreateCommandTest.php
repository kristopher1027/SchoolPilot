<?php

namespace Tests\Feature;

use App\Models\Platform\ProvisioningLog;
use App\Models\Platform\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Providers\TenancyServiceProvider;
use Database\Seeders\Tenant\DatabaseSeeder as TenantDatabaseSeeder;
use Stancl\Tenancy\Events;
use Tests\TestCase;

class SchoolCreateCommandTest extends TestCase
{
    public function test_school_create_rejects_invalid_slug_before_provisioning(): void
    {
        $this->artisan('school:create', [
            'name' => 'Example School',
            'slug' => '../outside',
            'email' => 'admin@example.test',
        ])->assertExitCode(1);
    }

    public function test_school_create_rejects_invalid_administrator_email_before_provisioning(): void
    {
        $this->artisan('school:create', [
            'name' => 'Example School',
            'slug' => 'example-school',
            'email' => 'not-an-email',
        ])->assertExitCode(1);
    }

    public function test_school_create_rejects_empty_school_name_before_provisioning(): void
    {
        $this->artisan('school:create', [
            'name' => '   ',
            'slug' => 'example-school',
            'email' => 'admin@example.test',
        ])->assertExitCode(1);
    }

    public function test_configured_tenant_seeder_exists(): void
    {
        $this->assertTrue(class_exists(TenantDatabaseSeeder::class));
    }

    public function test_provisioning_logs_use_the_central_database_connection(): void
    {
        $this->assertSame('central_sqlite', (new ProvisioningLog())->getConnectionName());
    }

    public function test_all_platform_migrations_are_bound_to_the_central_connection(): void
    {
        require_once database_path('migrations/2019_09_15_000010_create_tenants_table.php');
        require_once database_path('migrations/2019_09_15_000020_create_domains_table.php');

        $migrations = [
            new \CreateTenantsTable(),
            new \CreateDomainsTable(),
            require database_path('migrations/2026_10_26_084706_create_platform_admins_table.php'),
            require database_path('migrations/2026_10_26_084707_create_provisioning_logs_table.php'),
            require database_path('migrations/2026_10_26_084708_create_subscriptions_table.php'),
        ];

        foreach ($migrations as $migration) {
            $this->assertSame('central_sqlite', $migration->getConnection());
        }
    }

    public function test_school_create_provisions_a_tenant_database_and_first_administrator(): void
    {
        $centralDatabase = tempnam(sys_get_temp_dir(), 'schoolpilot-central-');
        $tenantDatabase = database_path('tenants/integration-school.sqlite');
        $previousCentralDatabase = config('database.connections.central_sqlite.database');
        $previousTenantDatabase = config('database.connections.tenant_sqlite.database');
        $previousDefault = config('database.default');

        try {
            config(['database.connections.central_sqlite.database' => $centralDatabase]);
            DB::purge('central_sqlite');

            Schema::connection('central_sqlite')->create('tenants', function ($table): void {
                $table->string('id')->primary();
                $table->timestamps();
                $table->json('data')->nullable();
            });

            Schema::connection('central_sqlite')->create('provisioning_logs', function ($table): void {
                $table->id();
                $table->string('school_id')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->string('action');
                $table->string('status')->default('success');
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            $this->artisan('school:create', [
                'name' => 'Integration School',
                'slug' => 'integration-school',
                'email' => 'admin@integration-school.test',
            ])
                ->expectsQuestion('Set a temporary password for the school administrator (minimum 12 characters)', 'Secure-Test-Password-2026')
                ->expectsQuestion('Confirm administrator password', 'Secure-Test-Password-2026')
                ->assertExitCode(0);

            $tenant = Tenant::query()->where('data->slug', 'integration-school')->first();

            $this->assertNotNull($tenant);
            $this->assertSame('integration-school.sqlite', $tenant->db_name);
            $this->assertFileExists($tenantDatabase);

            config(['database.connections.tenant_sqlite.database' => $tenantDatabase]);
            DB::purge('tenant_sqlite');

            $administrator = DB::connection('tenant_sqlite')
                ->table('users')
                ->where('email', 'admin@integration-school.test')
                ->first();

            $this->assertNotNull($administrator);
            $this->assertSame('school_admin', $administrator->role);
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Secure-Test-Password-2026', $administrator->password));
            $this->assertSame('Integration School', DB::connection('tenant_sqlite')->table('settings')->where('key', 'school_name')->value('value'));
            $this->assertTrue(ProvisioningLog::query()->where('school_id', $tenant->id)->where('status', 'success')->exists());
        } finally {
            DB::purge('tenant_sqlite');
            DB::purge('central_sqlite');
            config([
                'database.default' => $previousDefault,
                'database.connections.central_sqlite.database' => $previousCentralDatabase,
                'database.connections.tenant_sqlite.database' => $previousTenantDatabase,
            ]);

            if (is_file($tenantDatabase)) {
                @unlink($tenantDatabase);
            }
            if (is_file($centralDatabase)) {
                @unlink($centralDatabase);
            }
        }
    }

    public function test_school_create_removes_tenant_database_when_central_registration_fails(): void
    {
        $centralDatabase = tempnam(sys_get_temp_dir(), 'schoolpilot-central-');
        $tenantDatabase = database_path('tenants/rollback-school.sqlite');
        $previousCentralDatabase = config('database.connections.central_sqlite.database');
        $previousTenantDatabase = config('database.connections.tenant_sqlite.database');
        $previousDefault = config('database.default');

        try {
            config(['database.connections.central_sqlite.database' => $centralDatabase]);
            DB::purge('central_sqlite');

            Schema::connection('central_sqlite')->create('tenants', function ($table): void {
                $table->string('id')->primary();
                $table->string('required_registration_value');
                $table->timestamps();
                $table->json('data')->nullable();
            });

            Schema::connection('central_sqlite')->create('provisioning_logs', function ($table): void {
                $table->id();
                $table->string('school_id')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->string('action');
                $table->string('status')->default('success');
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            $this->artisan('school:create', [
                'name' => 'Rollback School',
                'slug' => 'rollback-school',
                'email' => 'admin@rollback-school.test',
            ])
                ->expectsQuestion('Set a temporary password for the school administrator (minimum 12 characters)', 'Secure-Test-Password-2026')
                ->expectsQuestion('Confirm administrator password', 'Secure-Test-Password-2026')
                ->assertExitCode(1);

            $this->assertFileDoesNotExist($tenantDatabase);
            $this->assertFalse(Tenant::query()->where('data->slug', 'rollback-school')->exists());
            $this->assertTrue(ProvisioningLog::query()->where('action', 'create')->where('status', 'failed')->exists());
        } finally {
            DB::purge('tenant_sqlite');
            DB::purge('central_sqlite');
            config([
                'database.default' => $previousDefault,
                'database.connections.central_sqlite.database' => $previousCentralDatabase,
                'database.connections.tenant_sqlite.database' => $previousTenantDatabase,
            ]);

            if (is_file($tenantDatabase)) {
                @unlink($tenantDatabase);
            }
            if (is_file($centralDatabase)) {
                @unlink($centralDatabase);
            }
        }
    }

    public function test_platform_migrations_write_only_to_central_database_when_default_connection_differs(): void
    {
        $centralDatabase = tempnam(sys_get_temp_dir(), 'schoolpilot-central-');
        $defaultDatabase = tempnam(sys_get_temp_dir(), 'schoolpilot-default-');
        $previousCentralDatabase = config('database.connections.central_sqlite.database');
        $previousDefaultDatabase = config('database.connections.sqlite.database');
        $previousDefaultConnection = config('database.default');

        try {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $defaultDatabase,
                'database.connections.central_sqlite.database' => $centralDatabase,
            ]);

            DB::purge('sqlite');
            DB::purge('central_sqlite');

            require_once database_path('migrations/2019_09_15_000010_create_tenants_table.php');
            require_once database_path('migrations/2019_09_15_000020_create_domains_table.php');

            $migrations = [
                new \\CreateTenantsTable(),
                new \\CreateDomainsTable(),
                require database_path('migrations/2026_10_26_084706_create_platform_admins_table.php'),
                require database_path('migrations/2026_10_26_084707_create_provisioning_logs_table.php'),
                require database_path('migrations/2026_10_26_084708_create_subscriptions_table.php'),
            ];

            foreach ($migrations as $migration) {
                $migration->up();
            }

            foreach (['tenants', 'domains', 'platform_admins', 'provisioning_logs', 'subscriptions'] as $table) {
                $this->assertTrue(
                    Schema::connection('central_sqlite')->hasTable($table),
                    "Expected [{$table}] to be created in the central database."
                );
                $this->assertFalse(
                    Schema::connection('sqlite')->hasTable($table),
                    "Did not expect [{$table}] to be created in the default database."
                );
            }
        } finally {
            DB::purge('sqlite');
            DB::purge('central_sqlite');

            config([
                'database.default' => $previousDefaultConnection,
                'database.connections.sqlite.database' => $previousDefaultDatabase,
                'database.connections.central_sqlite.database' => $previousCentralDatabase,
            ]);

            if (is_file($centralDatabase)) {
                @unlink($centralDatabase);
            }

            if (is_file($defaultDatabase)) {
                @unlink($defaultDatabase);
            }
        }
    }

    public function test_stancl_does_not_run_default_database_jobs_for_custom_tenant_provisioning(): void
    {
        $provider = new TenancyServiceProvider($this->app);
        $listeners = $provider->events();

        $this->assertSame([], $listeners[Events\TenantCreated::class]);
        $this->assertSame([], $listeners[Events\TenantDeleted::class]);
    }
}
