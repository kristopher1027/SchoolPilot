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

    public function test_payroll_approval_requires_item_count_and_all_totals_to_match(): void
    {
        $controller = new PayrollController();
        $method = new \\ReflectionMethod($controller, 'payrollTotalsMatch');
        $method->setAccessible(true);

        $payroll = (object) [
            'staff_count' => 2,
            'gross_total' => '500000.00',
            'deductions_total' => '25000.00',
            'net_total' => '475000.00',
        ];
        $matchingTotals = (object) [
            'item_count' => 2,
            'gross_total' => '500000.00',
            'deductions_total' => '25000.00',
            'net_total' => '475000.00',
        ];

        $this->assertTrue($method->invoke($controller, $payroll, $matchingTotals));

        $wrongCount = clone $matchingTotals;
        $wrongCount->item_count = 1;
        $this->assertFalse($method->invoke($controller, $payroll, $wrongCount));

        $wrongGross = clone $matchingTotals;
        $wrongGross->gross_total = '499999.99';
        $this->assertFalse($method->invoke($controller, $payroll, $wrongGross));

        $wrongDeductions = clone $matchingTotals;
        $wrongDeductions->deductions_total = '24999.99';
        $this->assertFalse($method->invoke($controller, $payroll, $wrongDeductions));

        $wrongNet = clone $matchingTotals;
        $wrongNet->net_total = '474999.99';
        $this->assertFalse($method->invoke($controller, $payroll, $wrongNet));
    }

}
