<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'goods_receipt_items_receipt_po_item_unique';

    public function up(): void
    {
        $duplicates = DB::table('goods_receipt_items')
            ->select('goods_receipt_id', 'purchase_order_item_id')
            ->groupBy('goods_receipt_id', 'purchase_order_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $pairs = $duplicates->map(fn ($row) => $row->goods_receipt_id . ':' . $row->purchase_order_item_id);
            throw new RuntimeException('Cannot enforce one receipt line per purchase-order line; duplicate pairs: ' . $pairs->implode(', '));
        }

        $exists = collect(Schema::getIndexes('goods_receipt_items'))->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['goods_receipt_id', 'purchase_order_item_id']
        );

        if (! $exists) {
            Schema::table('goods_receipt_items', function (Blueprint $table): void {
                $table->unique(['goods_receipt_id', 'purchase_order_item_id'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        $exists = collect(Schema::getIndexes('goods_receipt_items'))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === self::INDEX
        );

        if ($exists) {
            Schema::table('goods_receipt_items', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
