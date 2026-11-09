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

    public function test_stancl_does_not_run_default_database_jobs_for_custom_tenant_provisioning(): void
    {
        $provider = new TenancyServiceProvider($this->app);
        $listeners = $provider->events();

        $this->assertSame([], $listeners[Events\TenantCreated::class]);
        $this->assertSame([], $listeners[Events\TenantDeleted::class]);
    }
}
