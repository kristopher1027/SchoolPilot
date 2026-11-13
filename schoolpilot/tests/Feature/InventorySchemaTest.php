<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');

        parent::tearDown();
    }

    public function test_inventory_procurement_and_asset_tables_can_be_created_and_rolled_back(): void
    {
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

        $migration = require database_path('migrations/tenant/2026_11_13_000002_create_inventory_assets_procurement_tables.php');
        $migration->up();

        foreach ([
            'inventory_categories',
            'stock_locations',
            'suppliers',
            'inventory_items',
            'inventory_stocks',
            'inventory_movements',
            'purchase_requests',
            'purchase_request_items',
            'purchase_orders',
            'purchase_order_items',
            'goods_receipts',
            'goods_receipt_items',
            'assets',
            'asset_maintenance',
        ] as $table) {
            $this->assertTrue(Schema::connection('tenant_sqlite')->hasTable($table), "Missing table: {$table}");
        }

        $migration->down();

        $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable('inventory_items'));
        $this->assertFalse(Schema::connection('tenant_sqlite')->hasTable('assets'));
    }
}
