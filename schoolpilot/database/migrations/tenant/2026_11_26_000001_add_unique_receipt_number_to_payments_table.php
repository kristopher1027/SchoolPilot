<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'payments_receipt_no_integrity_unique';

    public function up(): void
    {
        $duplicates = DB::table('payments')
            ->select('receipt_no')
            ->whereNotNull('receipt_no')
            ->groupBy('receipt_no')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->pluck('receipt_no');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot enforce unique receipt numbers. Resolve existing duplicate receipts first: '
                . $duplicates->implode(', ')
            );
        }

        // The initial tenant schema already declares receipt_no as unique.
        // Avoid creating a second unique index on databases provisioned from that schema.
        $receiptIsAlreadyUnique = collect(Schema::getIndexes('payments'))->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['receipt_no']
        );

        if ($receiptIsAlreadyUnique) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('receipt_no', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        $indexExists = collect(Schema::getIndexes('payments'))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === self::INDEX_NAME
        );

        if (! $indexExists) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
