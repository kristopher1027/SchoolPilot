<?php

namespace Tests\\Feature;

use App\\Http\\Controllers\\Tenant\\InventoryController;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use ReflectionMethod;
use Tests\\TestCase;

class InventoryStockMovementIntegrityTest extends TestCase
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

        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('location_id');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['item_id', 'location_id']);
        });
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');

        parent::tearDown();
    }

    public function test_first_stock_movement_creates_exactly_one_stock_row(): void
    {
        $this->assertSame([0.0, 12.5], $this->apply(10, 2, 'receipt', 12.5));
        $this->assertSame(1, DB::table('inventory_stocks')->count());
        $this->assertSame(12.5, (float) DB::table('inventory_stocks')->value('quantity'));
    }

    public function test_receipt_increases_existing_stock(): void
    {
        DB::table('inventory_stocks')->insert([
            'item_id' => 10, 'location_id' => 2, 'quantity' => 8,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame([8.0, 13.0], $this->apply(10, 2, 'receipt', 5));
        $this->assertSame(13.0, (float) DB::table('inventory_stocks')->value('quantity'));
    }

    public function test_issue_decreases_stock_when_quantity_is_available(): void
    {
        DB::table('inventory_stocks')->insert([
            'item_id' => 10, 'location_id' => 2, 'quantity' => 8,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame([8.0, 3.0], $this->apply(10, 2, 'issue', 5));
        $this->assertSame(3.0, (float) DB::table('inventory_stocks')->value('quantity'));
    }

    public function test_issue_cannot_make_stock_negative(): void
    {
        DB::table('inventory_stocks')->insert([
            'item_id' => 10, 'location_id' => 2, 'quantity' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertNull($this->apply(10, 2, 'issue', 4));
        $this->assertSame(3.0, (float) DB::table('inventory_stocks')->value('quantity'));
    }

    public function test_adjustment_sets_counted_stock_and_returns_the_delta(): void
    {
        DB::table('inventory_stocks')->insert([
            'item_id' => 10, 'location_id' => 2, 'quantity' => 8,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame([8.0, 6.5], $this->apply(10, 2, 'adjustment', 6.5));
        $this->assertSame(6.5, (float) DB::table('inventory_stocks')->value('quantity'));
    }

    private function apply(int $itemId, int $locationId, string $type, float $quantity): ?array
    {
        $controller = new InventoryController();
        $method = new ReflectionMethod($controller, 'applyStockMovement');

        return $method->invoke($controller, $itemId, $locationId, $type, $quantity);
    }
}
