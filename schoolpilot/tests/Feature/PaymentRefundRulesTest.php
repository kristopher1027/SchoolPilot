<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\FeeController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_invoice_payment_updates_paid_balance_and_status_atomically(): void
    {
        $this->createInvoiceTable();
        DB::table('invoices')->insert([
            'id' => 1, 'total_amount' => 1000, 'amount_paid' => 0,
            'balance' => 1000, 'status' => 'unpaid',
        ]);

        $this->assertTrue($this->invoke('applyInvoicePayment', 1, 350.25));
        $invoice = DB::table('invoices')->find(1);

        $this->assertSame(350.25, (float) $invoice->amount_paid);
        $this->assertSame(649.75, (float) $invoice->balance);
        $this->assertSame('partial', $invoice->status);
        $this->assertEqualsWithDelta(1000.00, (float) $invoice->amount_paid + (float) $invoice->balance, 0.001);
    }

    public function test_invoice_payment_cannot_exceed_outstanding_balance(): void
    {
        $this->createInvoiceTable();
        DB::table('invoices')->insert([
            'id' => 1, 'total_amount' => 100, 'amount_paid' => 75,
            'balance' => 25, 'status' => 'partial',
        ]);

        $this->assertFalse($this->invoke('applyInvoicePayment', 1, 25.01));
        $invoice = DB::table('invoices')->find(1);
        $this->assertSame(75.0, (float) $invoice->amount_paid);
        $this->assertSame(25.0, (float) $invoice->balance);
        $this->assertSame('partial', $invoice->status);
    }

    public function test_exact_final_payment_marks_invoice_paid(): void
    {
        $this->createInvoiceTable();
        DB::table('invoices')->insert([
            'id' => 1, 'total_amount' => 100, 'amount_paid' => 60,
            'balance' => 40, 'status' => 'partial',
        ]);

        $this->assertTrue($this->invoke('applyInvoicePayment', 1, 40));
        $invoice = DB::table('invoices')->find(1);
        $this->assertSame(100.0, (float) $invoice->amount_paid);
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_invoice_refund_reverses_paid_amount_and_restores_balance(): void
    {
        $this->createInvoiceTable();
        DB::table('invoices')->insert([
            'id' => 1, 'total_amount' => 100, 'amount_paid' => 100,
            'balance' => 0, 'status' => 'paid',
        ]);

        $this->assertTrue($this->invoke('applyInvoiceRefund', 1, 30.50));
        $invoice = DB::table('invoices')->find(1);
        $this->assertSame(69.5, (float) $invoice->amount_paid);
        $this->assertSame(30.5, (float) $invoice->balance);
        $this->assertSame('partial', $invoice->status);
        $this->assertEqualsWithDelta(100.00, (float) $invoice->amount_paid + (float) $invoice->balance, 0.001);
    }

    public function test_invoice_refund_cannot_reduce_paid_amount_below_zero(): void
    {
        $this->createInvoiceTable();
        DB::table('invoices')->insert([
            'id' => 1, 'total_amount' => 100, 'amount_paid' => 10,
            'balance' => 90, 'status' => 'partial',
        ]);

        $this->assertFalse($this->invoke('applyInvoiceRefund', 1, 10.01));
        $invoice = DB::table('invoices')->find(1);
        $this->assertSame(10.0, (float) $invoice->amount_paid);
        $this->assertSame(90.0, (float) $invoice->balance);
        $this->assertSame('partial', $invoice->status);
    }

    private function createInvoiceTable(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->string('status');
            $table->timestamps();
        });
    }

    private function invoke(string $methodName, ...$arguments)
    {
        $controller = new FeeController();
        $method = new ReflectionMethod($controller, $methodName);

        return $method->invoke($controller, ...$arguments);
    }
}
