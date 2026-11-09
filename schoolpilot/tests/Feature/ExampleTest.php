<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_root_redirects_to_the_demo_school_login(): void
    {
        $this->get('/')
            ->assertRedirect('/school/greenfield/login');
    }
}
