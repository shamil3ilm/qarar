<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sales_order_free_goods');
        Schema::dropIfExists('free_goods_conditions');

        Schema::create('free_goods_conditions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('condition_number')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id', 'fg_cond_cust_fk')->references('id')->on('contacts')->onDelete('set null');
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->foreign('customer_group_id', 'fg_cond_cg_fk')->references('id')->on('customer_groups')->onDelete('set null');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'fg_cond_prod_fk')->references('id')->on('products')->onDelete('cascade');
            $table->unsignedBigInteger('free_product_id')->nullable();
            $table->foreign('free_product_id', 'fg_cond_free_prod_fk')->references('id')->on('products')->onDelete('set null');
            $table->enum('free_goods_type', ['inclusive', 'exclusive'])->default('exclusive');
            $table->decimal('minimum_quantity', 18, 4);
            $table->decimal('free_quantity', 18, 4);
            $table->enum('calculation_type', ['quantity', 'percentage'])->default('quantity');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sales_order_free_goods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->foreign('sales_order_id', 'so_fg_so_fk')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->unsignedBigInteger('free_goods_condition_id');
            $table->foreign('free_goods_condition_id', 'so_fg_cond_fk')->references('id')->on('free_goods_conditions')->onDelete('cascade');
            $table->unsignedBigInteger('triggered_line_id');
            $table->foreign('triggered_line_id', 'so_fg_trigger_fk')->references('id')->on('sales_order_lines')->onDelete('cascade');
            $table->unsignedBigInteger('free_product_id');
            $table->foreign('free_product_id', 'so_fg_prod_fk')->references('id')->on('products')->onDelete('cascade');
            $table->decimal('free_quantity', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_free_goods');
        Schema::dropIfExists('free_goods_conditions');
    }
};
