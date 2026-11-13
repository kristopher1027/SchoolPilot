<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category_type', 20)->default('stock');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['name', 'category_type']);
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('contact_person', 160)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['name', 'is_active']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('unit', 40)->default('piece');
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['name', 'is_active']);
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['item_id', 'location_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->string('movement_type', 24);
            $table->decimal('quantity', 12, 2);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('movement_at');
            $table->timestamps();
            $table->index(['item_id', 'location_id', 'movement_at']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 40)->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('estimated_unit_cost', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('ordered');
            $table->date('ordered_at');
            $table->date('expected_at')->nullable();
            $table->date('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'ordered_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity_ordered', 12, 2);
            $table->decimal('quantity_received', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_no', 40)->unique();
            $table->date('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->decimal('quantity_received', 12, 2);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('asset_tag', 80)->unique();
            $table->string('name', 180);
            $table->string('serial_no', 120)->nullable();
            $table->text('description')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('condition', 24)->default('good');
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['status', 'location_id']);
        });

        Schema::create('asset_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('scheduled');
            $table->text('description');
            $table->decimal('cost', 12, 2)->default(0);
            $table->date('service_date')->nullable();
            $table->date('next_service_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_service_date']);
        });
    }

    public function down(): void
    {
        foreach ([
            'asset_maintenance', 'assets', 'goods_receipt_items', 'goods_receipts',
            'purchase_order_items', 'purchase_orders', 'purchase_request_items',
            'purchase_requests', 'inventory_movements', 'inventory_stocks',
            'inventory_items', 'suppliers', 'stock_locations', 'inventory_categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
