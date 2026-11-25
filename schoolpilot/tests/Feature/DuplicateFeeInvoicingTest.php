<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\FeeController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DuplicateFeeInvoicingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.tenant_sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');
        Schema::create('invoices',function(Blueprint $table):void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_session_id');
            $table->unsignedBigInteger('term_id');
        });
        Schema::create('invoice_items',function(Blueprint $table):void {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('fee_item_id');
        });
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_lookup_finds_fee_items_previously_invoiced_for_same_student_session_and_term(): void
    {
        DB::table('invoices')->insert([
            ['id'=>1,'student_id'=>25,'academic_session_id'=>4,'term_id'=>7],
            ['id'=>2,'student_id'=>25,'academic_session_id'=>5,'term_id'=>8],
            ['id'=>3,'student_id'=>26,'academic_session_id'=>4,'term_id'=>7],
        ]);
        DB::table('invoice_items')->insert([
            ['invoice_id'=>1,'fee_item_id'=>100],
            ['invoice_id'=>2,'fee_item_id'=>101],
            ['invoice_id'=>3,'fee_item_id'=>102],
        ]);

        $found=$this->invoke('previouslyInvoicedFeeItemIds',25,4,7,[100,101,102]);

        $this->assertSame([100],$found);
        $this->assertSame([], $this->invoke('previouslyInvoicedFeeItemIds',25,4,7,[101]));
    }

    public function test_migration_enforces_one_occurrence_of_a_fee_item_per_invoice(): void
    {
        $migration=require database_path('migrations/tenant/2026_11_25_000002_unique_fee_item_per_invoice.php');
        $migration->up();

        DB::table('invoice_items')->insert(['invoice_id'=>1,'fee_item_id'=>10]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('invoice_items')->insert(['invoice_id'=>1,'fee_item_id'=>10]);
    }

    public function test_migration_can_be_rolled_back(): void
    {
        $migration=require database_path('migrations/tenant/2026_11_25_000002_unique_fee_item_per_invoice.php');
        $migration->up();
        $migration->down();
        DB::table('invoice_items')->insert([
            ['invoice_id'=>1,'fee_item_id'=>10],
            ['invoice_id'=>1,'fee_item_id'=>10],
        ]);
        $this->assertSame(2,DB::table('invoice_items')->count());
    }

    private function invoke(string $name,...$arguments)
    {
        $controller=new FeeController();
        $method=new ReflectionMethod($controller,$name);
        return $method->invoke($controller,...$arguments);
    }
}
