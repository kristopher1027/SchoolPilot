<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffHrSchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_staff_hr_tables_can_be_created_and_rolled_back(): void
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

        $migration = require database_path('migrations/tenant/2026_11_13_000005_create_staff_hr_attendance_leave_tables.php');
        $migration->up();

        foreach (['staff_attendance_records', 'staff_leave_types', 'staff_leave_requests'] as $table) {
            $this->assertTrue(Schema::connection('tenant_sqlite')->hasTable($table), "Missing table: {$table}");
        }

        $migration->down();

        foreach (['staff_attendance_records', 'staff_leave_types', 'staff_leave_requests'] as $table) {
            $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable($table), "Table was not rolled back: {$table}");
        }
    }
}
