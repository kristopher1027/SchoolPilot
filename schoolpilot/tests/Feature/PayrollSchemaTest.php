<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollSchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_payroll_tables_migrate_and_rollback_with_tenant_foreign_keys(): void
    {
        config([
            'database.connections.tenant_sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');

        Schema::connection('tenant_sqlite')->create('users', function (Blueprint $table) {
            $table->id();
        });
        Schema::connection('tenant_sqlite')->create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        });

        $migration = require database_path('migrations/tenant/2026_11_13_000006_create_payroll_management_tables.php');
        $migration->up();

        foreach (['salary_components', 'staff_salary_assignments', 'salary_assignment_components', 'payroll_periods', 'payroll_items'] as $table) {
            $this->assertTrue(Schema::connection('tenant_sqlite')->hasTable($table), "Missing table: {$table}");
        }

        $migration->down();

        foreach (['salary_components', 'staff_salary_assignments', 'salary_assignment_components', 'payroll_periods', 'payroll_items'] as $table) {
            $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable($table), "Table was not rolled back: {$table}");
        }
    }
}
