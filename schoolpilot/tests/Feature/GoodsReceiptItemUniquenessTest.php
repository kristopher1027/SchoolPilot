<?php

namespace Tests\\Feature;

use Illuminate\\Database\\QueryException;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use RuntimeException;
use Tests\\TestCase;

class GoodsReceiptItemUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant_sqlite' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->unsignedBigInteger('location_id');
            $table->decimal('quantity_received', 12, 2);
        });
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_migration_enforces_one_receipt_line_per_purchase_order_item_and_allows_other_lines(): void
    {
        $migration = require database_path('migrations/tenant/2026_11_28_000002_unique_goods_receipt_po_item.php');
        $migration->up();

        DB::table('goods_receipt_items')->insert([
            ['goods_receipt_id' => 1, 'purchase_order_item_id' => 10, 'location_id' => 1, 'quantity_received' => 4],
            ['goods_receipt_id' => 1, 'purchase_order_item_id' => 11, 'location_id' => 1, 'quantity_received' => 2],
            ['goods_receipt_id' => 2, 'purchase_order_item_id' => 10, 'location_id' => 1, 'quantity_received' => 3],
        ]);

        $this->expectException(QueryException::class);
        DB::table('goods_receipt_items')->insert([
            'goods_receipt_id' => 1, 'purchase_order_item_id' => 10, 'location_id' => 1, 'quantity_received' => 1,
        ]);
    }

    public function test_migration_detects_existing_duplicate_receipt_lines(): void
    {
        DB::table('goods_receipt_items')->insert([
            ['goods_receipt_id' => 1, 'purchase_order_item_id' => 10, 'location_id' => 1, 'quantity_received' => 2],
            ['goods_receipt_id' => 1, 'purchase_order_item_id' => 10, 'location_id' => 1, 'quantity_received' => 3],
        ]);

        $migration = require database_path('migrations/tenant/2026_11_28_000002_unique_goods_receipt_po_item.php');
        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    public function test_rollback_removes_only_its_own_index(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->unique(['goods_receipt_id', 'purchase_order_item_id'], 'preexisting_receipt_item_unique');
        });

        $migration = require database_path('migrations/tenant/2026_11_28_000002_unique_goods_receipt_po_item.php');
        $migration->up();
        $migration->down();

        DB::table('goods_receipt_items')->insert([
            'goods_receipt_id' => 7, 'purchase_order_item_id' => 70, 'location_id' => 1, 'quantity_received' => 1,
        ]);
        $this->expectException(QueryException::class);
        DB::table('goods_receipt_items')->insert([
            'goods_receipt_id' => 7, 'purchase_order_item_id' => 70, 'location_id' => 1, 'quantity_received' => 2,
        ]);
    }
}
