<?php

namespace Tests\Feature;

use App\Models\Platform\ProvisioningLog;
use Database\Seeders\Tenant\DatabaseSeeder as TenantDatabaseSeeder;
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
}
