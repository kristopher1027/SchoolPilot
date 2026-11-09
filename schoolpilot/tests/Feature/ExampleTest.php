<?php

namespace Tests\\Feature;

use Tests\\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_root_displays_the_public_schoolpilot_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('SchoolPilot')
            ->assertSee('Enter Demo School')
            ->assertSee('/school/greenfield/login');
    }

    public function test_the_demo_school_root_redirects_to_its_login_page(): void
    {
        $this->get('/school/greenfield')
            ->assertRedirect('/school/greenfield/login');
    }
}
