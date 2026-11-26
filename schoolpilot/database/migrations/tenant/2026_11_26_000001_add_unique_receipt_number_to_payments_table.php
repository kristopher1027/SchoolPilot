<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
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

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('receipt_no', 'payments_receipt_no_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_receipt_no_unique');
        });
    }
};
