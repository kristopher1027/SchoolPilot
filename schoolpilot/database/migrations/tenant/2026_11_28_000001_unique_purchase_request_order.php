<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    private const INDEX = 'purchase_orders_purchase_request_unique';

    public function up(): void
    {
        $duplicates = DB::table('purchase_orders')
            ->select('purchase_request_id')
            ->whereNotNull('purchase_request_id')
            ->groupBy('purchase_request_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->pluck('purchase_request_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot enforce one order per purchase request; duplicate request IDs: '.$duplicates->implode(', '));
        }

        $hasIndex = collect(Schema::getIndexes('purchase_orders'))->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['purchase_request_id']
        );

        if (!$hasIndex) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->unique('purchase_request_id', self::INDEX);
            });
        }
    }

    public function down(): void
    {
        $hasIndex = collect(Schema::getIndexes('purchase_orders'))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === self::INDEX
        );

        if ($hasIndex) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
