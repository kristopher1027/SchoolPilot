<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('payments')
            ->select('reference')
            ->whereNotNull('reference')
            ->groupBy('reference')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->pluck('reference');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot enforce unique payment references. Resolve existing duplicate references first: '
                . $duplicates->implode(', ')
            );
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('reference', 'payments_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_reference_unique');
        });
    }
};
