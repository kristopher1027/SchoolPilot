<?php

namespace Tests\Feature;

use App\Http\Middleware\UseSchoolDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_root_displays_the_public_schoolpilot_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('SchoolPilot')
            ->assertSee('School Login')
            ->assertSee('/login');
    }

    public function test_the_school_root_requires_authentication(): void
    {
        // Tenant resolution is covered by UseSchoolDatabaseIsolationTest.
        // This test isolates the route's redirect behavior from tenant DB setup.
        $this->withoutMiddleware(UseSchoolDatabase::class)
            ->get('/school/greenfield')
            ->assertRedirect('/login');
    }
}
