<?php

namespace Tests\\Feature;

use Illuminate\\Database\\QueryException;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use RuntimeException;
use Tests\\TestCase;

class PurchaseRequestOrderUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant_sqlite' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_unique_request_order_migration_allows_multiple_unlinked_orders_and_rejects_duplicate_links(): void
    {
        $migration = require database_path('migrations/tenant/2026_11_28_000001_unique_purchase_request_order.php');
        $migration->up();

        DB::table('purchase_orders')->insert([
            ['purchase_request_id' => null],
            ['purchase_request_id' => null],
            ['purchase_request_id' => 42],
        ]);

        $this->expectException(QueryException::class);
        DB::table('purchase_orders')->insert(['purchase_request_id' => 42]);
    }

    public function test_migration_refuses_preexisting_duplicate_request_links(): void
    {
        DB::table('purchase_orders')->insert([
            ['purchase_request_id' => 99],
            ['purchase_request_id' => 99],
        ]);

        $migration = require database_path('migrations/tenant/2026_11_28_000001_unique_purchase_request_order.php');
        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    public function test_rollback_drops_only_its_named_index(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->unique('purchase_request_id', 'existing_request_order_unique');
        });

        $migration = require database_path('migrations/tenant/2026_11_28_000001_unique_purchase_request_order.php');
        $migration->up();
        $migration->down();

        DB::table('purchase_orders')->insert(['purchase_request_id' => 123]);
        $this->expectException(QueryException::class);
        DB::table('purchase_orders')->insert(['purchase_request_id' => 123]);
    }
}
