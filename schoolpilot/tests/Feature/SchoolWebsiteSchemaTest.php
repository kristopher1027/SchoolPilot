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
            'school_website_settings', 'school_website_pages', 'school_website_posts', 'school_website_gallery_items',
            'school_events', 'school_event_registrations', 'school_clubs', 'school_club_members',
        ] as $table) {
            $this->assertTrue(Schema::connection('tenant_sqlite')->hasTable($table), "Missing table: {$table}");
        }

        $userId = DB::table('users')->insertGetId([]);
        $eventId = DB::table('school_events')->insertGetId([
            'title' => 'Open Day', 'slug' => 'open-day', 'description' => 'School open day',
            'starts_at' => now()->addDays(5), 'registration_open' => true, 'status' => 'published',
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['cancelled', 'registered'] as $status) {
            DB::table('school_event_registrations')->insert([
                'event_id' => $eventId, 'attendee_name' => 'Test Parent', 'email' => 'parent@example.test',
                'attendee_type' => 'parent', 'status' => $status, 'attendance_status' => 'not_marked',
                'registered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->assertSame(2, DB::table('school_event_registrations')->where('event_id', $eventId)->count());

        $migration->down();

        $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable('school_website_settings'));
        $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable('school_event_registrations'));
    }
}
