<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\PayrollController;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
{
    public function test_fixed_salary_component_returns_the_configured_amount(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'componentAmount');
        $method->setAccessible(true);

        $this->assertSame(12500.0, $method->invoke($controller, 250000.0, 'fixed', 12500.0));
    }

    public function test_percentage_component_is_calculated_from_base_salary_and_rounded(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'componentAmount');
        $method->setAccessible(true);

        $this->assertSame(25000.0, $method->invoke($controller, 250000.0, 'percentage', 10.0));
        $this->assertSame(333.34, $method->invoke($controller, 10000.0, 'percentage', 3.3334));
    }
}
