<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolExpenseSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.tenant_sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');
        Schema::connection('tenant_sqlite')->create('users',function(Blueprint $table):void{$table->id();$table->string('name');});
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_expense_schema_seeds_categories_and_enforces_reference_uniqueness(): void
    {
        $migration=require database_path('migrations/tenant/2026_11_25_000001_create_school_expenses_tables.php');
        $migration->up();
        $this->assertTrue(Schema::hasTable('expense_categories'));
        $this->assertTrue(Schema::hasTable('school_expenses'));
        $this->assertGreaterThanOrEqual(6,DB::table('expense_categories')->count());
        $category=DB::table('expense_categories')->first();
        DB::table('school_expenses')->insert(['category_id'=>$category->id,'description'=>'Electricity','amount'=>15000,'payment_method'=>'transfer','reference'=>'EXP-001','expense_date'=>'2026-11-25','status'=>'paid','paid_at'=>'2026-11-25','created_at'=>now(),'updated_at'=>now()]);
        DB::table('school_expenses')->insert(['category_id'=>$category->id,'description'=>'Stationery','amount'=>4000,'payment_method'=>'cash','reference'=>null,'expense_date'=>'2026-11-25','status'=>'unpaid','paid_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame(2,DB::table('school_expenses')->count());
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('school_expenses')->insert(['category_id'=>$category->id,'description'=>'Duplicate','amount'=>100,'payment_method'=>'cash','reference'=>'EXP-001','expense_date'=>'2026-11-25','status'=>'paid','paid_at'=>'2026-11-25','created_at'=>now(),'updated_at'=>now()]);
    }

    public function test_expense_schema_can_be_rolled_back(): void
    {
        $migration=require database_path('migrations/tenant/2026_11_25_000001_create_school_expenses_tables.php');
        $migration->up();
        $migration->down();
        $this->assertFalse(Schema::hasTable('school_expenses'));
        $this->assertFalse(Schema::hasTable('expense_categories'));
    }
}
