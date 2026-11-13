<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\FeeController;
use ReflectionMethod;
use Tests\TestCase;

class FeeReconciliationTest extends TestCase
{
    public function test_csv_export_escapes_formula_injection_values(): void
    {
        $controller = new FeeController();
        $method = new ReflectionMethod($controller, 'safeCsvValue');
        $method->setAccessible(true);

        $this->assertSame("'=1+1", $method->invoke($controller, '=1+1'));
        $this->assertSame("'+SUM(A1:A2)", $method->invoke($controller, '+SUM(A1:A2)'));
        $this->assertSame('Transfer reference', $method->invoke($controller, 'Transfer reference'));
        $this->assertSame(1250.50, $method->invoke($controller, 1250.50));
    }
}
