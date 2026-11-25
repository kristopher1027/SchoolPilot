<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('invoice_items')
            ->select('invoice_id', 'fee_item_id')
            ->groupBy('invoice_id', 'fee_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot enforce unique fee items per invoice. Resolve duplicate invoice item rows first.'
            );
        }

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->unique(['invoice_id', 'fee_item_id'], 'invoice_items_invoice_fee_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropUnique('invoice_items_invoice_fee_item_unique');
        });
    }
};
