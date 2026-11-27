<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use RuntimeException;
use Tests\TestCase;

class RefundSettlementReferenceSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant_sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');

        Schema::connection('tenant_sqlite')->create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('settlement_reference')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_settlement_reference_migration_enforces_unique_references_and_allows_nulls(): void
    {
        $migration = require database_path('migrations/tenant/2026_11_27_000001_add_unique_refund_settlement_reference.php');
        $migration->up();

        DB::table('payment_refunds')->insert(['settlement_reference' => 'BANK-REF-001']);
        DB::table('payment_refunds')->insert(['settlement_reference' => null]);
        DB::table('payment_refunds')->insert(['settlement_reference' => null]);

        $this->expectException(QueryException::class);
        DB::table('payment_refunds')->insert(['settlement_reference' => 'BANK-REF-001']);
    }

    public function test_settlement_reference_migration_refuses_existing_duplicate_references(): void
    {
        DB::table('payment_refunds')->insert([
            ['settlement_reference' => 'DUPLICATE-REF'],
            ['settlement_reference' => 'DUPLICATE-REF'],
        ]);

        $migration = require database_path('migrations/tenant/2026_11_27_000001_add_unique_refund_settlement_reference.php');

        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    public function test_settlement_reference_migration_rollback_drops_only_its_own_index(): void
    {
        Schema::connection('tenant_sqlite')->table('payment_refunds', function (Blueprint $table): void {
            $table->unique('settlement_reference', 'preexisting_settlement_reference_unique');
        });

        $migration = require database_path('migrations/tenant/2026_11_27_000001_add_unique_refund_settlement_reference.php');
        $migration->up();
        $migration->down();

        DB::table('payment_refunds')->insert(['settlement_reference' => 'BASELINE-REF']);

        $this->expectException(QueryException::class);
        DB::table('payment_refunds')->insert(['settlement_reference' => 'BASELINE-REF']);
    }
}
