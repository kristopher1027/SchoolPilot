<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'payment_refunds_settlement_reference_unique';

    public function up(): void
    {
        $duplicates = DB::table('payment_refunds')
            ->select('settlement_reference')
            ->whereNotNull('settlement_reference')
            ->where('settlement_reference', '!=', '')
            ->groupBy('settlement_reference')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->pluck('settlement_reference');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot enforce unique refund settlement references. Resolve duplicate references first: '
                . $duplicates->implode(', ')
            );
        }

        $alreadyUnique = collect(Schema::getIndexes('payment_refunds'))->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['settlement_reference']
        );

        if ($alreadyUnique) {
            return;
        }

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->unique('settlement_reference', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        $exists = collect(Schema::getIndexes('payment_refunds'))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === self::INDEX_NAME
        );

        if (! $exists) {
            return;
        }

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
