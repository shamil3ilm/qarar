<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_where_used_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('inventory_batch_id');
            $table->foreign('inventory_batch_id', 'bwu_batch_fk')->references('id')->on('inventory_batches');
            $table->enum('usage_type', ['work_order', 'process_order', 'sales_invoice', 'stock_transfer', 'adjustment'])->default('work_order');
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_number', 100)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'bwu_product_fk')->references('id')->on('products');
            $table->decimal('quantity_used', 18, 4);
            $table->dateTime('used_at');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'bwu_warehouse_fk')->references('id')->on('warehouses');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by', 'bwu_recorded_by_fk')->references('id')->on('users');
            $table->timestamps();

            $table->index(['inventory_batch_id'], 'bwu_batch_idx');
            $table->index(['usage_type', 'reference_id'], 'bwu_ref_idx');
            $table->index(['organization_id', 'used_at'], 'bwu_org_used_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_where_used_records');
    }
};
