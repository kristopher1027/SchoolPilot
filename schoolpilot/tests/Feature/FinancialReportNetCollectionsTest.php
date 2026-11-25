<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\ReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class FinancialReportNetCollectionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.tenant_sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');
        Schema::create('students',function(Blueprint $t){$t->id();$t->unsignedBigInteger('class_id')->nullable();$t->string('status')->default('active');});
        Schema::create('classes',function(Blueprint $t){$t->id();$t->string('name');});
        DB::table('classes')->insert(['id'=>10,'name'=>'Primary 1']);
        Schema::create('invoices',function(Blueprint $t){$t->id();$t->unsignedBigInteger('student_id');$t->unsignedBigInteger('academic_session_id');$t->unsignedBigInteger('term_id');$t->decimal('total_amount',12,2);$t->decimal('amount_paid',12,2);$t->decimal('balance',12,2);$t->date('created_at')->nullable();});
        Schema::create('payments',function(Blueprint $t){$t->id();$t->unsignedBigInteger('invoice_id');$t->decimal('amount',12,2);$t->date('paid_at');});
        Schema::create('payment_refunds',function(Blueprint $t){$t->id();$t->unsignedBigInteger('payment_id');$t->decimal('amount',12,2);$t->string('status');});
        DB::table('students')->insert(['id'=>1,'class_id'=>10,'status'=>'active']);
        DB::table('invoices')->insert([
            ['id'=>1,'student_id'=>1,'academic_session_id'=>3,'term_id'=>4,'total_amount'=>1000,'amount_paid'=>700,'balance'=>300,'created_at'=>'2026-11-20'],
            ['id'=>2,'student_id'=>1,'academic_session_id'=>5,'term_id'=>6,'total_amount'=>500,'amount_paid'=>500,'balance'=>0,'created_at'=>'2026-11-20'],
        ]);
        DB::table('payments')->insert([
            ['id'=>11,'invoice_id'=>1,'amount'=>1000,'paid_at'=>'2026-11-21'],
            ['id'=>12,'invoice_id'=>2,'amount'=>500,'paid_at'=>'2026-11-21'],
        ]);
        DB::table('payment_refunds')->insert([
            ['payment_id'=>11,'amount'=>200,'status'=>'approved'],
            ['payment_id'=>11,'amount'=>100,'status'=>'settled'],
            ['payment_id'=>11,'amount'=>50,'status'=>'pending'],
            ['payment_id'=>11,'amount'=>40,'status'=>'rejected'],
            ['payment_id'=>12,'amount'=>500,'status'=>'settled'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_report_collected_totals_subtract_approved_and_settled_refunds_but_not_pending_or_rejected(): void
    {
        $data=$this->buildReport(['from'=>null,'to'=>null,'session_id'=>null,'term_id'=>null,'class_id'=>null]);
        $this->assertSame(700.0,$data['finance']['collected']);
        $this->assertSame(2,$data['finance']['payments']);
        $this->assertSame(1500.0,$data['finance']['invoiced']);
        $this->assertSame(300.0,$data['finance']['outstanding']);
    }

    public function test_session_filter_applies_to_invoice_payment_and_refund_totals(): void
    {
        $data=$this->buildReport(['from'=>null,'to'=>null,'session_id'=>3,'term_id'=>null,'class_id'=>null]);
        $this->assertSame(700.0,$data['finance']['collected']);
        $this->assertSame(1,$data['finance']['payments']);
        $this->assertSame(1000.0,$data['finance']['invoiced']);
    }

    private function buildReport(array $filters): array
    {
        $controller=new ReportController();
        $method=new ReflectionMethod($controller,'buildReportData');
        return $method->invoke($controller,$filters);
    }
}
