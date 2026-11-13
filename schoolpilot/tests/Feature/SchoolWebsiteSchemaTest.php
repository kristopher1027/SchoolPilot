<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolWebsiteSchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_school_website_and_events_tables_can_be_created_and_rolled_back(): void
    {
        config(['database.connections.tenant_sqlite' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');

        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('staff', fn (Blueprint $table) => $table->id());
        Schema::create('students', fn (Blueprint $table) => $table->id());

        $migration = require database_path('migrations/tenant/2026_11_13_000004_create_school_website_and_events_tables.php');
        $migration->up();

        foreach ([
            'school_website_settings', 'school_website_pages', 'school_website_posts',
            'school_events', 'school_event_registrations', 'school_clubs', 'school_club_members',
        ] as $table) {
            $this->assertTrue(Schema::connection('tenant_sqlite')->hasTable($table), "Missing table: {$table}");
        }

        $migration->down();

        $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable('school_website_settings'));
        $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable('school_event_registrations'));
    }
}
