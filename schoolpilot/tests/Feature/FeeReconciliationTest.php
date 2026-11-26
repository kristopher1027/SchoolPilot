<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\FeeController;
use Illuminate\\Support\\Facades\\Validator;
use ReflectionMethod;
use Tests\TestCase;

class FeeReconciliationTest extends TestCase
{
    public function test_payment_and_refund_amount_rule_rejects_more_than_two_decimal_places(): void
    {
        $controller = new FeeController();
        $method = new ReflectionMethod($controller, 'moneyAmountRules');
        $rules = $method->invoke($controller);

        $this->assertTrue(Validator::make(['amount' => '125.50'], ['amount' => $rules])->passes());
        $this->assertFalse(Validator::make(['amount' => '125.501'], ['amount' => $rules])->passes());
        $this->assertFalse(Validator::make(['amount' => '0.001'], ['amount' => $rules])->passes());
        $this->assertFalse(Validator::make(['amount' => '0'], ['amount' => $rules])->passes());
    }

    public function test_csv_export_escapes_formula_injection_values(): void
    {
        $controller = new FeeController();
        $method = new ReflectionMethod($controller, 'safeCsvValue');
        $method->setAccessible(true);

        $this->assertSame("'=1+1", $method->invoke($controller, '=1+1'));
        $this->assertSame("'  =1+1", $method->invoke($controller, '  =1+1'));
        $this->assertSame("'\t+SUM(A1:A2)", $method->invoke($controller, "\t+SUM(A1:A2)"));
        $this->assertSame("'+SUM(A1:A2)", $method->invoke($controller, '+SUM(A1:A2)'));
        $this->assertSame('Transfer reference', $method->invoke($controller, 'Transfer reference'));
        $this->assertSame(1250.50, $method->invoke($controller, 1250.50));
    }
}
