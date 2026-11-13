<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\FeeController;
use ReflectionMethod;
use Tests\TestCase;

class PaymentRefundRulesTest extends TestCase
{
    public function test_refundable_amount_reserves_pending_and_approved_refunds(): void
    {
        $this->assertSame(450.25, $this->invoke('refundableAmount', 1000.00, 300.00, 249.75));
    }

    public function test_refundable_amount_never_becomes_negative(): void
    {
        $this->assertSame(0.0, $this->invoke('refundableAmount', 500.00, 400.00, 200.00));
    }

    public function test_refund_reviewer_must_be_different_from_requester(): void
    {
        $this->assertTrue($this->invoke('canReviewRefund', 10, 11));
        $this->assertFalse($this->invoke('canReviewRefund', 10, 10));
    }

    private function invoke(string $methodName, ...$arguments)
    {
        $controller = new FeeController();
        $method = new ReflectionMethod($controller, $methodName);

        return $method->invoke($controller, ...$arguments);
    }
}
