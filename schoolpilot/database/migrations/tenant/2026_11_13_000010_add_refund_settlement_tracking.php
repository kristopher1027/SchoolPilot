<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->string('settlement_method', 30)->nullable();
            $table->string('settlement_reference', 120)->nullable();
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->index(['status', 'settled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->dropIndex(['status', 'settled_at']);
            $table->dropColumn([
                'settlement_method',
                'settlement_reference',
                'settled_by',
                'settled_at',
            ]);
        });
    }
};
