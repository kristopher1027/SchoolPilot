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

    public function test_reserved_refund_totals_include_approved_and_settled_but_not_rejected_requests(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20);
        });

        DB::table('payment_refunds')->insert([
            ['payment_id' => 7, 'amount' => 100, 'status' => 'approved'],
            ['payment_id' => 7, 'amount' => 50, 'status' => 'settled'],
            ['payment_id' => 7, 'amount' => 25, 'status' => 'pending'],
            ['payment_id' => 7, 'amount' => 200, 'status' => 'rejected'],
            ['payment_id' => 8, 'amount' => 500, 'status' => 'settled'],
        ]);

        $totals = $this->invoke('reservedRefundTotals', 7);

        $this->assertSame(['accepted' => 150.0, 'pending' => 25.0], $totals);
        $this->assertSame(825.0, $this->invoke('refundableAmount', 1000.0, $totals['accepted'], $totals['pending']));
    }

    public function test_refund_reviewer_must_be_different_from_requester(): void
    {
        $this->assertTrue($this->invoke('canReviewRefund', 10, 11));
        $this->assertFalse($this->invoke('canReviewRefund', 10, 10));
    }

    public function test_refund_settlement_requires_a_third_person(): void
    {
        $this->assertTrue($this->invoke('canSettleRefund', 10, 11, 12));
        $this->assertFalse($this->invoke('canSettleRefund', 10, 11, 10));
        $this->assertFalse($this->invoke('canSettleRefund', 10, 11, 11));
    }

    private function invoke(string $methodName, ...$arguments)
    {
        $controller = new FeeController();
        $method = new ReflectionMethod($controller, $methodName);

        return $method->invoke($controller, ...$arguments);
    }
}
