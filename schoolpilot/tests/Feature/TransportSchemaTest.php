<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransportSchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_transport_tables_can_be_created_and_rolled_back(): void
    {
        config(['database.connections.tenant_sqlite' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');

        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('students', fn (Blueprint $table) => $table->id());
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
        });

        $migration = require database_path('migrations/tenant/2026_11_13_000003_create_transport_management_tables.php');
        $migration->up();

        foreach ([
            'transport_vehicles', 'transport_routes', 'transport_stops', 'transport_route_stops',
            'transport_student_assignments', 'transport_vehicle_documents', 'transport_trips',
            'transport_trip_attendance', 'transport_incidents', 'transport_vehicle_maintenance',
        ] as $table) {
            $this->assertTrue(Schema::connection('tenant_sqlite')->hasTable($table), "Missing table: {$table}");
        }

        $migration->down();

        $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable('transport_vehicles'));
        $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable('transport_student_assignments'));
    }
}
