<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\PayrollController;
use ReflectionMethod;
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

    public function test_payroll_creator_cannot_approve_their_own_period(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'canApprovePayroll');
        $method->setAccessible(true);
        $payroll = (object) ['created_by' => 17];

        $this->assertFalse($method->invoke($controller, $payroll, 17));
        $this->assertTrue($method->invoke($controller, $payroll, 23));
    }

    public function test_legacy_payroll_without_creator_can_be_approved_by_an_authorized_manager(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'canApprovePayroll');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, (object) ['created_by' => null], 23));
    }

    public function test_payroll_approver_cannot_mark_the_same_period_as_paid(): void
    {
        $controller = new PayrollController();
        $method = new ReflectionMethod($controller, 'canMarkPayrollPaid');
        $method->setAccessible(true);
        $payroll = (object) ['approved_by' => 17];

        $this->assertFalse($method->invoke($controller, $payroll, 17));
        $this->assertTrue($method->invoke($controller, $payroll, 23));
    }

    public function test_legacy_payroll_without_approver_can_be_marked_paid_by_an_authorized_manager(): void
    {
        $controller = new PayrollController();
        $method = new ReflectionMethod($controller, 'canMarkPayrollPaid');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, (object) ['approved_by' => null], 23));
    }

    public function test_payroll_approval_requires_item_count_and_all_totals_to_match(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'payrollTotalsMatch');
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
